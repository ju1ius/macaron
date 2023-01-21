<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests;

use ju1ius\Macaron\Cookie;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

final class CookieTest extends TestCase
{
    /**
     * @dataProvider ofProvider
     */
    public function testOf(mixed $input, Cookie $expected): void
    {
        Assert::assertEquals($expected, Cookie::of($input));
    }

    public static function ofProvider(): iterable
    {
        yield 'cookie' => [
            $input = new Cookie('foo', 'bar'),
            $input,
        ];
        yield 'http-foundation' => [
            new HttpCookie('foo', 'bar', raw: true),
            new Cookie('foo', 'bar', samesite: 'lax'),
        ];
        yield 'browser-kit' => [
            new BrowserKitCookie('foo', 'bar'),
            new Cookie('foo', 'bar'),
        ];
    }

    public function testOfThrowsTypeError(): void
    {
        $this->expectException(\TypeError::class);
        Cookie::of(new class{});
    }

    public function testAddCaster(): void
    {
        $cookie = new class {
            public string $name = 'foo';
            public string $value = 'bar';
        };
        Cookie::addCaster($cookie::class, fn($c) => new Cookie($c->name, $c->value));
        $expected = new Cookie($cookie->name, $cookie->value);
        Assert::assertEquals($expected, Cookie::of($cookie));
    }
}
