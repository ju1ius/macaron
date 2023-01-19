<?php declare(strict_types=1);

namespace ju1ius\Macaron;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\TransportException;
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
        if ($cookies = $options['extra']['cookies'] ?? null) {
            return $this->requestWithCookieJar($method, $url, $options, CookieJar::of($cookies));
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
        do {
            // FIXME: $url is wrong on 1st request when using 'base_uri' option with a relative URI
            $options['headers']['cookie'] = $jar->asCookieHeader($url);
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
}
