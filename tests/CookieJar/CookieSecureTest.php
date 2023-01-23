<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\CookieJar;

use ju1ius\Macaron\CookieJar;
use ju1ius\Macaron\Http\HttpMethod;
use ju1ius\Macaron\Uri\Uri;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CookieSecureTest extends TestCase
{
    #[DataProvider('secureProvider')]
    public function testSecure(array $setCookie, string $origin, string $request, string $expected): void
    {
        $jar = new CookieJar();
        $method = HttpMethod::Get;
        $jar->updateFromGenericResponse($method, Uri::of($origin), 200, $setCookie);
        $result = $jar->retrieveForGenericRequest($method, Uri::of($request));
        Assert::assertSame($expected, $result);
    }

    public static function secureProvider(): iterable
    {
        yield 'secure cookie set from http is not sent to https' => [
            ['test=1; secure; path=/'],
            'http://wpt.test/',
            'https://wpt.test/',
            '',
        ];
        yield 'secure cookie set from https is not sent to https' => [
            ['test=1; secure; path=/'],
            'https://wpt.test/',
            'https://wpt.test/',
            'test=1',
        ];
        yield 'secure cookie set from https is not sent to http' => [
            ['test=1; secure; path=/'],
            'https://wpt.test/',
            'http://wpt.test/',
            '',
        ];
        yield 'secure cookie set from http is not sent to wss' => [
            ['test=1; secure; path=/'],
            'http://wpt.test/',
            'wss://wpt.test/',
            '',
        ];
        yield 'secure cookie set from https is sent to wss' => [
            ['test=1; secure; path=/'],
            'https://wpt.test/',
            'wss://wpt.test/',
            'test=1',
        ];
        yield 'secure cookie set from https is not sent to ws' => [
            ['test=1; secure; path=/'],
            'https://wpt.test/',
            'ws://wpt.test/',
            '',
        ];
    }
}
