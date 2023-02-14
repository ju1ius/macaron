<?php declare(strict_types=1);

namespace Souplette\Macaron\Bridge\Symfony;

use Psr\Clock\ClockInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Souplette\Macaron\Clock\UTCClock;
use Souplette\Macaron\Cookie;
use Souplette\Macaron\Cookie\CookiePath;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Http\HttpMethod;
use Souplette\Macaron\Http\RequestChain;
use Souplette\Macaron\Uri\UriService;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

final class MacaronHttpClient implements HttpClientInterface, LoggerAwareInterface, ResetInterface
{
    use HttpClientTrait;

    public const OPTION_KEY = 'macaron';

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
        // FIXME: we should get these values from the decorated client instead!
        [$url, $options] = self::prepareRequest($method, $url, $options, self::OPTIONS_DEFAULTS, true);
        $uri = $this->uriService->createUri(implode('', $url));
        $method = HttpMethod::of($method);

        if ($jar = $this->createCookieJar($method, $uri, $options)) {
            return $this->requestWithCookieJar($method, $uri, $options, $jar);
        }
        return $this->client->request($method->value, (string)$uri, $options);
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

    private function createCookieJar(HttpMethod $method, UriInterface $uri, array $options): ?CookieJar
    {
        $this->initialCookies = [];
        if (null === $factory = $options['extra'][self::OPTION_KEY] ?? null) {
            return null;
        }
        if (\is_callable($factory)) {
            if (!$factory = $factory($method, $uri, $options)) {
                return null;
            }
        }
        if ($factory instanceof CookieJar) {
            return $factory;
        }

        $jar = new CookieJar($this->uriService, clock: $this->clock);
        if (\is_array($factory) || $factory instanceof \Traversable) {
            $now = $this->clock->now();
            foreach ($factory as $name => $value) {
                if ($value instanceof Cookie) {
                    if ($value->domain === '') {
                        $value->domain = $uri->getHost();
                    }
                    if ($value->path === '') {
                        $value->path = CookiePath::default($uri);
                    }
                    $jar->store($value);
                } else if (\is_string($name) && \is_scalar($value)) {
                    $cookie = new Cookie(
                        $name,
                        (string)$value,
                        $uri->getHost(),
                        CookiePath::default($uri),
                        httpOnly: true,
                        createdAt: $now,
                    );
                    $this->initialCookies[] = $cookie;
                    $jar->store($cookie);
                }
            }
        }
        return $jar;
    }

    private function requestWithCookieJar(
        HttpMethod $method,
        UriInterface $uri,
        array $options,
        CookieJar $jar
    ): ResponseInterface {
        $maxRedirects = $options['max_redirects'];
        $options['max_redirects'] = 0;
        $numRedirects = 0;
        $state = new ClientState($options);
        $chain = new RequestChain($this->uriService);
        $chain->start($uri);
        do {
            $cookie = $jar->retrieveForGenericRequest($method, $uri, $chain->isSameSite());
            if ($cookie !== '') {
                $options['headers']['cookie'] = $cookie;
            }
            $response = $this->client->request($method->value, (string)$uri, $options);
            /**
             * @see https://www.rfc-editor.org/rfc/rfc9110#name-redirection-3xx
             */
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $jar->updateFromGenericResponse(
                $method,
                $uri,
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
}
