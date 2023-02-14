<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\Cookie;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Souplette\Macaron\Cookie\ParseError;
use Souplette\Macaron\Cookie\ResponseCookie;
use Souplette\Macaron\Cookie\SameSite;
use Souplette\Macaron\Cookie\SetCookieParser;
use Symfony\Component\Clock\MockClock;

final class SetCookieParserTest extends TestCase
{
    private static function parse(string $input): ResponseCookie
    {
        $parser = new SetCookieParser(self::getClock());
        return $parser->parse($input);
    }

    private static function getClock(): ClockInterface
    {
        static $clock;
        return $clock ??= new MockClock();
    }

    #[DataProvider('parseProvider')]
    public function testParse(string $input, ResponseCookie $expected): void
    {
        $cookie = self::parse($input);
        Assert::assertEquals($expected, $cookie);
    }

    public static function parseProvider(): iterable
    {
        // Name/Value pair
        yield 'no name' => ['bar', new ResponseCookie('', 'bar')];
        yield 'no value' => ['foo=', new ResponseCookie('foo', '')];
        yield 'name/value' => ['foo=bar', new ResponseCookie('foo', 'bar')];
        yield 'ignores whitespace' => [' foo = bar ', new ResponseCookie('foo', 'bar')];
        yield 'does not unquote value' => ['foo="bar"', new ResponseCookie('foo', '"bar"')];
        yield 'handles "=" in value' => ['foo=bar=baz', new ResponseCookie('foo', 'bar=baz')];
        yield 'ignores unknown attributes' => [
            'a=b; no; nada=zilch; niet=;',
            new ResponseCookie('a', 'b'),
        ];
        yield 'does not decode values' => ['a+b=c%20d', new ResponseCookie('a+b', 'c%20d')];
        // Domain
        yield 'empty domain' => ['a=b; domain', new ResponseCookie('a', 'b', domain: '')];
        yield 'empty domain value' => ['a=b; domain=', new ResponseCookie('a', 'b', domain: '')];
        yield 'strips leading dots' => [
            'a=b; domain=.example.com',
            new ResponseCookie('a', 'b', domain: 'example.com'),
        ];
        // Path
        yield 'path default' => ['a=b', new ResponseCookie('a', 'b', path: null)];
        yield 'empty path' => ['a=b; path', new ResponseCookie('a', 'b', path: null)];
        yield 'empty path value' => ['a=b; path=', new ResponseCookie('a', 'b', path: null)];
        yield 'invalid path' => ['a=b; path=well', new ResponseCookie('a', 'b', path: null)];
        yield 'path = /' => ['a=b; path=/', new ResponseCookie('a', 'b', path: '/')];
        yield 'path = /a/b' => ['a=b; path = /a/b', new ResponseCookie('a', 'b', path: '/a/b')];
        // SameSite
        yield 'SameSite=' => [
            'a=b; SameSite = ',
            new ResponseCookie('a', 'b', sameSite: SameSite::Default),
        ];
        yield 'SameSite=Lax' => [
            'a=b; SameSite = Lax',
            new ResponseCookie('a', 'b', sameSite: SameSite::Lax),
        ];
        yield 'SameSite=Strict' => [
            'a=b; samesite=strict',
            new ResponseCookie('a', 'b', sameSite: SameSite::Strict),
        ];
        yield 'SameSite=None' => [
            'a=b; SameSite=NONE',
            new ResponseCookie('a', 'b', sameSite: SameSite::None),
        ];
        // HttpOnly
        yield 'HttpOnly default' => ['a=b', new ResponseCookie('a', 'b', httpOnly: false)];
        yield 'HttpOnly' => ['a=b; hTTpOnly', new ResponseCookie('a', 'b', httpOnly: true)];
        yield 'HttpOnly value' => ['a=b; HttpOnly=whatever', new ResponseCookie('a', 'b', httpOnly: true)];
        // Secure
        yield 'Secure default' => ['a=b', new ResponseCookie('a', 'b', secure: false)];
        yield 'Secure' => ['a=b; Secure', new ResponseCookie('a', 'b', secure: true)];
        yield 'Secure value' => ['a=b; secure=whatever', new ResponseCookie('a', 'b', secure: true)];
        // DATES!
        $unix = new \DateTimeImmutable('@0', new \DateTimeZone('UTC'));
        $now = self::getClock()->now();
        // Max-Age
        yield 'max-age default' => ['a=b', new ResponseCookie('a', 'b', maxAge: null)];
        yield 'empty max-age' => ['a=b; max-age', new ResponseCookie('a', 'b', maxAge: null)];
        yield 'invalid max-age' => ['a=b; Max-Age=nope', new ResponseCookie('a', 'b', maxAge: null)];
        yield 'max-age=0' => ['a=b; Max-Age=0', new ResponseCookie('a', 'b', maxAge: 0)];
        yield 'max-age < 0' => ['a=b; max-age = -1', new ResponseCookie('a', 'b', maxAge: -1)];
        yield 'max-age > 0' => [
            'a=b; max-age = 42',
            new ResponseCookie('a', 'b', maxAge: 42),
        ];
        // Expires
        yield 'expires default' => ['a=b', new ResponseCookie('a', 'b', expires: null)];
        yield 'empty expires' => ['a=b; expires;', new ResponseCookie('a', 'b', expires: null)];
        yield 'empty expires value' => ['a=b; expires = ', new ResponseCookie('a', 'b', expires: null)];
        yield 'invalid expires value' => ['a=b; expires = wait?', new ResponseCookie('a', 'b', expires: null)];
        yield 'expires date' => [
            'a=b; expires = Dec 24th 1979 02:00:00',
            new ResponseCookie('a', 'b', expires: new \DateTimeImmutable('1979-12-24 02:00:00')),
        ];
    }

    public function testItRejectsInvalidCharacters(): void
    {
        $this->expectException(ParseError::class);
        self::parse("a=b\x00c");
    }

    public function testItRejectsLargeAttributeValues(): void
    {
        $payload = str_repeat('0', 1024);
        // valid date, but rejected because value is > 1024 bytes
        $input = 'a=b; expires = Jan 01 1970 0:0:0 ' . $payload;
        $cookie = self::parse($input);
        $expected = new ResponseCookie('a', 'b');
        Assert::assertEquals($expected, $cookie);
    }

    public function testItRejectsLargeNameValuePair(): void
    {
        $payload = str_repeat('A', 2048);
        $input = $payload . '=' . $payload . '0';
        $this->expectException(ParseError::class);
        self::parse($input);
    }
}
