<?php declare(strict_types=1);

namespace ju1ius\Macaron;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class CookieAwareHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($cookies = $options['extra']['cookies'] ?? null) {
            return $this->requestWithCookieJar($method, $url, $options, $cookies);
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

    private function requestWithCookieJar(string $method, string $url, array $options, array $cookies): ResponseInterface
    {
        $jar = self::createCookieJar($cookies);
        // FIXME: we should get the value from the decorated client!
        $maxRedirects = $options['max_redirects'] ?? HttpClientInterface::OPTIONS_DEFAULTS['max_redirects'];
        $options['max_redirects'] = 0;
        $numRedirects = 0;
        do {
            // FIXME: $url is wrong on 1st request when using 'base_uri' option with a relative URI
            $options['headers']['cookie'] = self::getCookieHeader($jar, $url);
            $response = $this->client->request($method, $url, $options);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $jar->updateFromSetCookie($headers['set-cookie'] ?? [], $response->getInfo('url'));
            if ($status >= 300 && $status < 400) {
                $numRedirects++;
                if (!$location = $headers['location'][0] ?? null) {
                    throw new TransportException(sprintf(
                        'Missing response header "Location" for [%d] %s',
                        $status,
                        $url,
                    ));
                }
                $url = $location;
                if (\in_array($status, [301, 302, 303], true)) {
                    $method = 'GET';
                    unset($options['query'], $options['body']);
                }
                continue;
            }
            return $response;
        } while ($numRedirects <= $maxRedirects);

        throw new RedirectionException($response);
    }

    private static function getCookieHeader(CookieJar $jar, string $uri): string
    {
        $cookies = [];
        foreach ($jar->allRawValues($uri) as $name => $value) {
            $cookies[] = "{$name}={$value}";
        }

        return implode('; ', $cookies);
    }

    private static function createCookieJar(array $cookies): CookieJar
    {
        $jar = new CookieJar();
        foreach ($cookies as $key => $value) {
            if ($cookie = match (true) {
                $value instanceof Cookie => $value,
                \is_scalar($value) => new Cookie($key, (string)$value),
                default => null,
            }) {
                $jar->set($cookie);
            }
        }

        return $jar;
    }
}
