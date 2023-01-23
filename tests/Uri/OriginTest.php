<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Uri;

use ju1ius\Macaron\Exception\InvalidUriException;
use ju1ius\Macaron\Uri\Origin;
use ju1ius\Macaron\Uri\Uri;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OriginTest extends TestCase
{
    #[DataProvider('invalidOriginProvider')]
    public function testInvalidOrigin(string $scheme, string $host): void
    {
        $this->expectException(InvalidUriException::class);
        new Origin($scheme, $host, null);
    }

    public static function invalidOriginProvider(): iterable
    {
        yield 'no scheme' => ['', 'foo.test'];
        yield 'no host' => ['https', ''];
    }

    #[DataProvider('ofProvider')]
    public function testOf(mixed $input, Origin $expected): void
    {
        Assert::assertEquals($expected, Origin::of($input));
    }

    public static function ofProvider(): iterable
    {
        yield 'origin' => [
            $o = new Origin('http', 'a.b', 80),
            $o,
        ];
        yield 'uri' => [
            Uri::of('https://a.b:443/foo'),
            new Origin('https', 'a.b', 443),
        ];
    }

    #[DataProvider('getEffectiveDomainProvider')]
    public function testGetEffectiveDomain(Origin $origin, string $expected): void
    {
        Assert::assertSame($expected, $origin->getEffectiveDomain());
    }

    public static function getEffectiveDomainProvider(): iterable
    {
        yield [new Origin('a', 'b', null), 'b'];
        yield [new Origin('a', 'b', null, 'c'), 'c'];
    }

    #[DataProvider('sameOriginOrSameDomainProvider')]
    public function testSameOriginAndSameDomain(Origin $lhs, Origin $rhs, bool $sameOrigin): void
    {
        Assert::assertSame($sameOrigin, $lhs->isSameOrigin($rhs));
    }

    #[DataProvider('sameOriginOrSameDomainProvider')]
    public function testSameOriginDomain(Origin $lhs, Origin $rhs, bool $sameOrigin, bool $sameDomain): void
    {
        Assert::assertSame($sameDomain, $lhs->isSameOriginDomain($rhs));
    }

    public static function sameOriginOrSameDomainProvider(): iterable
    {
        yield 'identity' => [
            $a = new Origin('a', 'b', null),
            $a,
            true,
            true,
        ];
        yield 'same scheme & host, null, null' => [
            new Origin('https', 'example.org', null, null),
            new Origin('https', 'example.org', null, null),
            true,
            true,
        ];
        yield 'different ports, null domain' => [
            new Origin('https', 'example.org', 314, null),
            new Origin('https', 'example.org', 420, null),
            false,
            false,
        ];
        yield 'different ports, same domain' => [
            new Origin('https', 'example.org', 314, 'example.org'),
            new Origin('https', 'example.org', 420, 'example.org'),
            false,
            true,
        ];
        yield 'different domains' => [
            new Origin('https', 'example.org', null, null),
            new Origin('https', 'example.org', null, 'example.org'),
            true,
            false,
        ];
        yield 'different schemes' => [
            new Origin('https', 'example.org', null, 'example.org'),
            new Origin('http', 'example.org', null, 'example.org'),
            false,
            false,
        ];
    }

    #[DataProvider('toStringProvider')]
    public function testToString(Origin $input, string $expected): void
    {
        Assert::assertSame($expected, (string)$input);
    }

    public static function toStringProvider(): iterable
    {
        yield [
            new Origin('sftp', 'a.b', 22),
            'sftp://a.b:22',
        ];
        yield [
            new Origin('gopher', 'a.b', null),
            'gopher://a.b'
        ];
    }
}
