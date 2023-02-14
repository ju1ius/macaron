<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\Cookie;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Souplette\Macaron\Cookie\Domain;
use Souplette\Macaron\Uri\Uri;

final class DomainTest extends TestCase
{
    public function testOfSelf(): void
    {
        $domain = Domain::of('foo.com');
        Assert::assertSame($domain, Domain::of($domain));
    }

    public function testOfUri(): void
    {
        $uri = Uri::of('http://foo.test/bar');
        $domain = Domain::of($uri);
        Assert::assertSame($uri->getHost(), (string)$domain);
    }

    #[DataProvider('isIpAddressProvider')]
    public function testIsIpAddress(string $input, bool $expected): void
    {
        Assert::assertSame($expected, Domain::of($input)->isIpAddress());
    }

    public static function isIpAddressProvider(): iterable
    {
        yield 'localhost' => ['localhost', false];
        yield 'localhost v4' => ['127.0.0.1', true];
        yield 'localhost v6' => ['::ffff:7f00:0001', true];
        yield 'localhost v6 w/ bracket' => ['[::ffff:7f00:0001]', true];
        yield 'invalid IP' => ['1.1.1.300', false];
    }

    #[DataProvider('equalsProvider')]
    public function testEquals(string $lhs, string $rhs, bool $expected): void
    {
        $result = Domain::of($lhs)->equals(Domain::of($rhs));
        Assert::assertSame($expected, $result);
    }

    public static function equalsProvider(): iterable
    {
        yield 'strict equal' => [
            'foo.bar.com',
            'foo.bar.com',
            true,
        ];
        yield 'ascii case-insensitive' => [
            'Foo.bAr.CoM',
            'foo.bar.com',
            true,
        ];
        yield 'unicode case-insensitive' => [
            'ÇÀ.ÉÈ.com',
            'çà.éè.com',
            true,
        ];
        yield 'no unicode to ascii conversion' => [
            'faß.de',
            'fass.de',
            false,
        ];
    }

    #[DataProvider('matchesProvider')]
    public function testMatches(string $lhs, string $rhs, bool $expected): void
    {
        $result = Domain::of($lhs)->matches($rhs);
        Assert::assertSame($expected, $result);
    }

    public static function matchesProvider(): iterable
    {
        yield ['foo.com', 'foo.com', true];
        yield ['bar.foo.com', 'foo.com', true];
        yield ['baz.bar.foo.com', 'foo.com', true];

        yield ['bar.foo.com', 'bar.com', false];
        yield ['bar.com', 'baz.bar.com', false];
        yield ['foo.com', 'bar.com', false];

        yield ['bar.com', 'bbar.com', false];
        yield ['bbar.com', 'bar.com', false];

        yield ['127.0.0.1', '127.0.0.1', true];
        yield ['127.0.0.1', '1.1.1.1', false];
        yield ['127.0.0.1', '0.1', false];
        yield ['::ffff:7f00:0001', '::ffff:7f00:0001', true];
        yield ['::ffff:7f00:0001', ':0001', false];
    }

    #[DataProvider('toStringProvider')]
    public function testToString(string $input): void
    {
        Assert::assertSame($input, (string)Domain::of($input));
    }

    public static function toStringProvider(): iterable
    {
        yield ['foo.com'];
        yield ['Foo.cOm'];
        yield ['ÉÀéà.test'];
    }
}
