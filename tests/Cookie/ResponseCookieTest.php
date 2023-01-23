<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Cookie;

use ju1ius\Macaron\Cookie\ResponseCookie;
use ju1ius\Macaron\Cookie\SameSite;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseCookieTest extends TestCase
{
    #[DataProvider('toStringProvider')]
    public function testToString(ResponseCookie $cookie, string $expected): void
    {
        Assert::assertSame($expected, (string)$cookie);
    }

    public static function toStringProvider(): iterable
    {
        $unix = new \DateTimeImmutable('@0');
        $unixString = 'Thu, 01 Jan 1970 00:00:00 GMT';

        yield 'no-attributes' => [
            new ResponseCookie('a', 'b'),
            'a=b',
        ];
        yield 'domain' => [
            new ResponseCookie('a', 'b', 'a.b.test'),
            'a=b; domain=a.b.test',
        ];
        yield 'path' => [
            new ResponseCookie('a', 'b', path: '/foo'),
            'a=b; path=/foo',
        ];
        yield 'expires' => [
            new ResponseCookie('a', 'b', expires: $unix),
            'a=b; expires=' . $unixString,
        ];
        yield 'max-age' => [
            new ResponseCookie('a', 'b', maxAge: 666),
            'a=b; max-age=666',
        ];
        yield 'secure' => [
            new ResponseCookie('a', 'b', secure: true),
            'a=b; secure',
        ];
        yield 'httponly' => [
            new ResponseCookie('a', 'b', httpOnly: true),
            'a=b; httponly',
        ];
        yield 'samesite' => [
            new ResponseCookie('a', 'b', sameSite: SameSite::None),
            'a=b; samesite=none',
        ];
        yield 'kitchen sink' => [
            new ResponseCookie('a', 'b', 'c.d', '/e/f', $unix, 42, true, true, SameSite::Strict),
            "a=b; domain=c.d; path=/e/f; expires={$unixString}; max-age=42; secure; httponly; samesite=strict",
        ];
    }
}
