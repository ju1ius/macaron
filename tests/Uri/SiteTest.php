<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Uri;

use ju1ius\Macaron\Exception\InvalidUriException;
use ju1ius\Macaron\Uri\Site;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteTest extends TestCase
{
    #[DataProvider('invalidSiteProvider')]
    public function testInvalidSite(string $scheme, string $host): void
    {
        $this->expectException(InvalidUriException::class);
        new Site($scheme, $host);
    }

    public static function invalidSiteProvider(): iterable
    {
        yield ['a', ''];
        yield ['', 'b'];
        yield ['', ''];
    }

    #[DataProvider('provideSameSite')]
    public function testSameSite(Site $a, Site $b, bool $expected): void
    {
        Assert::assertSame($expected, $a->isSameSite($b));
    }

    public static function provideSameSite(): iterable
    {
        yield [$a = new Site('foo', 'bar'), $a, true];
        yield [new Site('foo', 'bar'), new Site('foo', 'bar'), true];
        yield [new Site('foo', 'bar'), new Site('foo', 'baz'), false];
        yield [new Site('foo', 'bar'), new Site('baz', 'bar'), false];
        yield [new Site('FOO', 'bar'), new Site('baz', 'bar'), false];
    }

    #[DataProvider('toStringProvider')]
    public function testToString(Site $input, string $expected): void
    {
        Assert::assertSame($expected, (string)$input);
    }

    public static function toStringProvider(): iterable
    {
        yield [new Site('foo', 'bar'), 'foo://bar'];
    }
}
