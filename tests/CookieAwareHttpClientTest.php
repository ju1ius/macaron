<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests;

use ju1ius\Macaron\Cookie;
use ju1ius\Macaron\CookieAwareHttpClient;
use ju1ius\Macaron\CookieJar;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
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

    public function testReset(): void
    {
        $client = new CookieAwareHttpClient($inner = new MockHttpClient(fn() => new MockResponse()));
        $client->request('GET', '/');
        Assert::assertSame(1, $inner->getRequestsCount());
        $client->reset();
        Assert::assertSame(0, $inner->getRequestsCount());
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

    public function testRequestWithCookieArray(): void
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
            'extra' => ['cookies' => ['foo' => 'bar']],
        ]);
        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame('OK', $response->getContent());
    }

    public function testRequestWithCookieJar(): void
    {
        $jar = new CookieJar();
        $factory = function() use ($jar) {
            Assert::assertEmpty($jar->all());
            yield self::redirect(307, 'http://macaron.test/b', [new Cookie('a', '1')]);
            yield self::redirect(302, 'http://macaron.test/c', [new Cookie('b', '1')]);
            yield new MockResponse('OK');
        };
        $client = self::createClient($factory());
        $response = $client->request('GET', '/', ['extra' => ['cookies' => $jar]]);
        Assert::assertSame(200, $response->getStatusCode());

        $expected = [
            new Cookie('a', '1', path: '/', domain: 'macaron.test'),
            new Cookie('b', '1', path: '/', domain: 'macaron.test'),
        ];
        Assert::assertEquals($expected, $jar->all());
    }

    public function testStream(): void
    {
        $factory = function() {
            yield self::redirect(307, 'http://macaron.test/b');
            yield new MockResponse('KO', [
                'http_code' => 302,
                'response_headers' => ['location' => 'http://macaron.test/c']
            ]);
            yield new MockResponse('OK');
        };
        $client = self::createClient($factory());
        $response = $client->request('GET', '/', ['extra' => ['cookies' => new CookieJar()]]);
        Assert::assertSame(200, $response->getStatusCode());
        $body = '';
        foreach ($client->stream($response) as $chunk) {
            $body .= $chunk->getContent();
        }
        Assert::assertSame('OK', $body);
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
            'extra' => [
                'cookies' => [
                    'foo' => 'bar',
                    'bar' => new class {
                    },
                ],
            ],
        ]);
    }

    public function testSetLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $inner = new class($this) extends MockHttpClient implements LoggerAwareInterface {
            public function __construct(private TestCase $test)
            {
                parent::__construct();
            }

            public function setLogger(LoggerInterface $logger): void
            {
                $this->test->addToAssertionCount(1);
            }
        };
        $client = new CookieAwareHttpClient($inner);
        $client->setLogger($logger);
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
