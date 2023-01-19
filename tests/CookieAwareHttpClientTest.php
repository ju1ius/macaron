<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests;

use ju1ius\Macaron\CookieAwareHttpClient;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;

final class CookieAwareHttpClientTest extends TestCase
{
    public function testWithOptions(): void
    {
        $client = self::createClient(null);
        Assert::assertInstanceOf(CookieAwareHttpClient::class, $client->withOptions([]));
    }

    public function testRequestWithoutCookies(): void
    {
        // when no cookies are set, the inner client request method is called exactly once.
        $factory = [
            self::redirect(307, '/a'),
            self::redirect(307, '/b'),
            new MockResponse('OK'),
        ];
        $client = new CookieAwareHttpClient($inner = new MockHttpClient($factory));
        $response = $client->request('GET', '/foo');
        Assert::assertSame(307, $response->getStatusCode());
        Assert::assertSame(1, $inner->getRequestsCount());
    }

    public function testRequestWithCookies(): void
    {
        $factory = function(string $method, string $url, array $options = []) {
            $cookie = $options['normalized_headers']['cookie'] ?? null;
            switch ($url) {
                case 'http://macaron.test/a':
                    Assert::assertSame(['cookie: foo=bar'], $cookie);
                    return self::redirect(307, 'http://macaron.test/b', [new Cookie('a', '1')]);
                case 'http://macaron.test/b':
                    Assert::assertSame(['cookie: foo=bar; a=1'], $cookie);
                    return self::redirect(302, 'http://macaron.test/c', [new Cookie('b', '1')]);
                case 'http://macaron.test/c':
                    Assert::assertSame(['cookie: foo=bar; a=1; b=1'], $cookie);
                    return new MockResponse('OK');
                default:
                    return null;
            }
        };
        $client = self::createClient($factory);
        $response = $client->request('GET', '/a', [
            'max_redirects' => 5,
            'extra' => ['cookies' => ['foo' => 'bar']]
        ]);
        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame('OK', $response->getContent());
    }

    public function testItFailsWhenMissingLocationHeader(): void
    {
        $client = self::createClient(fn() => new MockResponse('', ['http_code' => 307]));
        $this->expectException(TransportException::class);
        $client->request('GET', '/foo', [
            'extra' => ['cookies' => ['a' => 'b']],
        ]);
    }

    public function testItFailsWhenTooManyRedirects(): void
    {
        $client = self::createClient([
            self::redirect(307, '/a'),
            self::redirect(307, '/b'),
        ]);
        $this->expectException(RedirectionExceptionInterface::class);
        $client->request('GET', '/foo', [
            'max_redirects' => 1,
            'extra' => ['cookies' => ['a' => 'b']],
        ]);
    }

    public function testItSkipsInvalidCookies(): void
    {
        $client = self::createClient(function(string $method, string $url, array $options = []) {
            $cookie = $options['normalized_headers']['cookie'] ?? null;
            Assert::assertSame(['cookie: foo=bar'], $cookie);
            return new MockResponse('OK');
        });
        $client->request('GET', '/', [
            'extra' => ['cookies' => ['foo' => 'bar', 'bar' => new class{}]],
        ]);
    }

    private static function createClient($factory): CookieAwareHttpClient
    {
        $client = new MockHttpClient($factory, 'http://macaron.test');
        return new CookieAwareHttpClient($client);
    }

    /**
     * @param Cookie[] $cookies
     */
    private static function redirect(int $code, string $location, array $cookies = []): MockResponse
    {
        return new MockResponse('', [
            'http_code' => $code,
            'response_headers' => [
                'location' => $location,
                'set-cookie' => array_map(fn($c) => (string)$c, $cookies),
            ],
        ]);
    }
}
