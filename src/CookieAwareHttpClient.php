<?php declare(strict_types=1);

namespace ju1ius\Macaron;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

final class CookieAwareHttpClient implements HttpClientInterface, LoggerAwareInterface, ResetInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
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
        return new self($this->client->withOptions($options));
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
        if (!$factory = $options['extra']['cookies'] ?? null) {
            return null;
        }
        if (\is_callable($factory)) {
            return CookieJar::of($factory($method, $url, $options));
        }

        return CookieJar::of($factory);
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
        $hostOptions = new HostOptions();
        do {
            // FIXME: $url is wrong on 1st request when using 'base_uri' option with a relative URI
            $options['headers']['cookie'] = $jar->asCookieHeader($url);
            $response = $this->client->request($method, $url, $options);
            /**
             * @see https://www.rfc-editor.org/rfc/rfc9110#name-redirection-3xx
             */
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $effectiveUri = $response->getInfo('url');
            $hostOptions->storeForUri($effectiveUri, $options);
            $jar->updateFromSetCookie($headers['set-cookie'] ?? [], $effectiveUri);
            if ($status >= 300 && $status < 400 && $location = $response->getInfo('redirect_url')) {
                $numRedirects++;
                $url = $location;
                $options = $hostOptions->updateForRedirect($location, $options);
                if (\in_array($status, [301, 302, 303], true)) {
                    switch ($method) {
                        case 'HEAD':
                        case 'GET':
                            break;
                        default:
                            $method = 'GET';
                            unset($options['query'], $options['body']);
                            break;
                    }
                }
                continue;
            }
            return $response;
        } while ($numRedirects <= $maxRedirects);

        throw new RedirectionException($response);
    }
}
