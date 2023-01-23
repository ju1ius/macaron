<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Cookie;

use ju1ius\Macaron\Cookie\CookiePath;
use ju1ius\Macaron\Uri\Uri;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CookiePathTest extends TestCase
{
    #[DataProvider('defaultPathProvider')]
    public function testDefaultPath(string $uri, string $expected): void
    {
        $result = CookiePath::default(Uri::of($uri));
        Assert::assertSame($expected, $result);
    }

    public static function defaultPathProvider(): iterable
    {
        yield ['/foo/bar/baz/', '/foo/bar/baz'];
        yield ['/foo/bar/baz', '/foo/bar'];
        yield ['/foo/', '/foo'];
        yield ['/foo', '/'];
        yield ['/', '/'];
        yield ['', '/'];
        yield ['foo', '/'];
    }

    #[DataProvider('matchesProvider')]
    public function testMatches(string $uri, string $path, bool $expected): void
    {
        Assert::assertSame($expected, CookiePath::matches(Uri::of($uri), $path));
    }

    public static function matchesProvider(): iterable
    {
        yield ['/', '/', true];
        yield ['/index.html', '/', true];
        yield ['/w/index.html', '/', true];
        yield ['/w/index.html', '/w/index.html', true];
        yield ['/w/index.html', '/w/', true];
        yield ['/w/index.html', '/w', true];

        yield ['/', '/w/', false];
        yield ['/a', '/w/', false];
        yield ['/', '/w', false];
        yield ['/w/index.html', '/w/index', false];
        yield ['/windex.html', '/w/', false];
        yield ['/windex.html', '/w', false];
    }
}
