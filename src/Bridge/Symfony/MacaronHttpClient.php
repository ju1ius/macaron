<?php declare(strict_types=1);

namespace ju1ius\Macaron\Bridge\Symfony;

use ju1ius\Macaron\Clock\UTCClock;
use ju1ius\Macaron\Cookie;
use ju1ius\Macaron\Cookie\CookiePath;
use ju1ius\Macaron\CookieJar;
use ju1ius\Macaron\Http\HttpMethod;
use ju1ius\Macaron\Http\RequestChain;
use ju1ius\Macaron\Uri\UriService;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

final class MacaronHttpClient implements HttpClientInterface, LoggerAwareInterface, ResetInterface
{
    /**
     * @var Cookie[]
     */
    private array $initialCookies = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly UriService $uriService,
        private readonly ClockInterface $clock = new UTCClock(),
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($jar = $this->createCookieJar($method, $url, $options)) {
            return $this->requestWithCookieJar($method, $url, $options, $jar);
        }
        return $this->client->request($method, $url, $options);
    }

    public function stream(iterable|ResponseInterface $responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new self(
            $this->client->withOptions($options),
            $this->uriService,
            $this->clock,
        );
    }

    public function setLogger(LoggerInterface $logger): void
    {
        if ($this->client instanceof LoggerAwareInterface) {
            $this->client->setLogger($logger);
        }
    }

    public function reset(): void
    {
        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }

    private function createCookieJar(string $method, string $url, array $options): ?CookieJar
    {
        $this->initialCookies = [];
        if (null === $factory = $options['extra']['cookies'] ?? null) {
            return null;
        }
        if ($factory instanceof CookieJar) {
            return $factory;
        }
        if (\is_callable($factory)) {
            return $factory($method, $url, $options);
        }

        $jar = new CookieJar($this->uriService, clock: $this->clock);
        if (\is_array($factory) || $factory instanceof \Traversable) {
            $now = $this->clock->now();
            foreach ($factory as $name => $value) {
                if ($value instanceof Cookie) {
                    $jar->store($value);
                } else if (\is_string($name) && \is_scalar($value)) {
                    $cookie = new Cookie($name, (string)$value, persistent: false, httpOnly: true, createdAt: $now);
                    $this->initialCookies[] = $cookie;
                    $jar->store($cookie);
                }
            }
        }
        return $jar;
    }

    private function requestWithCookieJar(
        string $method,
        string $url,
        array $options,
        CookieJar $jar
    ): ResponseInterface {
        // FIXME: we should get the value from the decorated client!
        $maxRedirects = $options['max_redirects'] ?? HttpClientInterface::OPTIONS_DEFAULTS['max_redirects'];
        $options['max_redirects'] = 0;
        $numRedirects = 0;
        $method = HttpMethod::of($method);
        $uri = $this->uriService->createUri($url);
        $state = null;
        $chain = new RequestChain($this->uriService);
        do {
            // FIXME: $url is wrong on 1st request when using 'base_uri' option with a relative URI
            $options['headers']['cookie'] = $jar->retrieveForGenericRequest($method, $uri, $chain->isSameSite());
            $response = $this->client->request($method->value, (string)$uri, $options);
            /**
             * @see https://www.rfc-editor.org/rfc/rfc9110#name-redirection-3xx
             */
            $effectiveUri = $this->uriService->createUri($response->getInfo('url'));
            if (!$state) {
                $chain->start($effectiveUri);
                $this->upgradeInitialCookies($jar, $effectiveUri);
            }
            $state ??= new ClientState($options);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $jar->updateFromGenericResponse(
                $method,
                $effectiveUri,
                $status,
                $headers['set-cookie'] ?? [],
                $chain->isSameSite(),
            );
            if ($status >= 300 && $status < 400 && $location = $response->getInfo('redirect_url')) {
                $numRedirects++;
                $uri = $this->uriService->createUri($location);
                $chain->next($uri);
                [$method, $options] = $state->redirect($method, $status, $chain->isSameOrigin());
                continue;
            }

            $chain->finish();

            return $response;
        } while ($numRedirects <= $maxRedirects);

        throw new RedirectionException($response);
    }

    private function upgradeInitialCookies(CookieJar $jar, UriInterface $uri): void
    {
        foreach ($this->initialCookies as $cookie) {
            $jar->remove($cookie);
            $cookie->domain = $uri->getHost();
            $cookie->path = CookiePath::default($uri);
            $jar->store($cookie);
        }
        $this->initialCookies = [];
    }
}
