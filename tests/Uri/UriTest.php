<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Uri;

use ju1ius\Macaron\Exception\InvalidUriException;
use ju1ius\Macaron\Uri\Uri;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

final class UriTest extends TestCase
{
    #[DataProvider('invalidUriProvider')]
    public function testInvalidUri(string $uri): void
    {
        $this->expectException(InvalidUriException::class);
        Uri::of($uri);
    }

    public static function invalidUriProvider(): iterable
    {
        yield 'no host' => ['a://:80'];
    }

    public function testIdentity(): void
    {
        $uri = Uri::of('http://example.test');
        Assert::assertSame($uri, Uri::of($uri));
    }

    public function testFromUriInterface(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn('foo');
        $uri->method('getHost')->willReturn('a.b');
        $uri->method('getUserInfo')->willReturn('');
        $uri->method('getPath')->willReturn('bar');
        $uri->method('getQuery')->willReturn('baz');
        $uri->method('getFragment')->willReturn('qux');

        $self = Uri::of($uri);
        Assert::assertInstanceOf(Uri::class, $self);
        Assert::assertEquals('foo://a.b/bar?baz#qux', (string)$self);
    }

    public function testImmutability(): void
    {
        $uri = Uri::of('http://example.test');

        self::assertNotSame($uri, $uri->withScheme('https'));
        self::assertNotSame($uri, $uri->withUserInfo('user', 'pass'));
        self::assertNotSame($uri, $uri->withHost('example.com'));
        self::assertNotSame($uri, $uri->withPort(8080));
        self::assertNotSame($uri, $uri->withPath('/path/123'));
        self::assertNotSame($uri, $uri->withQuery('q=abc'));
        self::assertNotSame($uri, $uri->withFragment('test'));
    }

    #[DataProvider('defaultPortProvider')]
    public function testDefaultPort(UriInterface|string $scheme, ?int $expected): void
    {
        Assert::assertSame($expected, Uri::defaultPort($scheme));
    }

    public static function defaultPortProvider(): iterable
    {
        yield ['http', 80];
        yield ['https', 443];
        yield ['ws', 80];
        yield ['wss', 443];
        yield ['file', null];
        yield ['ftp', 21];
        yield [Uri::of('https://foo.bar'), 443];
    }

    #[DataProvider('provideInvalidArguments')]
    public function testInvalidArgumentErrors(string $method, ...$args): void
    {
        $uri = Uri::of('http://example.test');
        $this->expectException(\InvalidArgumentException::class);
        $uri->{$method}(...$args);
    }

    public static function provideInvalidArguments(): iterable
    {
        yield ['withScheme', null];
        yield ['withUserInfo', null, null];
        yield ['withUserInfo', 'me', 333];
        yield ['withHost', null];
        yield ['withPort', 'foo'];
        yield ['withPath', 42];
        yield ['withQuery', 33];
        yield ['withFragment', 666];
    }

    #[DataProvider('gettersProvider')]
    public function testGetters(string $uri, array $expected): void
    {
        $uri = Uri::of($uri);
        foreach ($expected as $name => $value) {
            $method = sprintf('get%s', ucfirst($name));
            Assert::assertSame($value, $uri->{$method}());
        }
    }

    public static function gettersProvider(): iterable
    {
        yield [
            'http://foo.bar/baz?qux=42#hash',
            [
                'scheme' => 'http',
                'host' => 'foo.bar',
                'authority' => 'foo.bar:80',
                'userInfo' => '',
                'port' => 80,
                'path' => '/baz',
                'query' => 'qux=42',
                'fragment' => 'hash',
            ],
        ];
        yield [
            'http://user:pass@example.com',
            [
                'scheme' => 'http',
                'host' => 'example.com',
                'authority' => 'user:pass@example.com:80',
                'userInfo' => 'user:pass',
                'port' => 80,
                'path' => '',
                'query' => '',
                'fragment' => '',
            ],
        ];
    }
}
