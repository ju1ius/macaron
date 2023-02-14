<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\CookieJar;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Http\HttpMethod;
use Souplette\Macaron\Uri\Uri;

final class CookieDomainTest extends TestCase
{
    #[DataProvider('domainProvider')]
    public function testDomain(array $setCookie, string $uri, string $expected): void
    {
        $jar = new CookieJar();
        $base = Uri::of('http://wpt.test/');
        $method = HttpMethod::Get;
        $jar->updateFromGenericResponse($method, $base, 200, $setCookie);
        $uri = Uri::of($uri);
        $result = $jar->retrieveForGenericRequest($method, $uri);
        Assert::assertSame($expected, $result);
    }

    public static function domainProvider(): iterable
    {
        yield 'domain matches host => sent with same-origin requests.' => [
            ['test=b; path=/; domain=wpt.test'],
            'http://wpt.test/cookies/domain',
            'test=b',
        ];
        yield 'domain matches host => sent with subdomain requests' => [
            ['test=b; path=/; domain=wpt.test'],
            'https://sub.wpt.test/cookies/domain',
            'test=b',
        ];
        yield 'domain with leading dot matches host => sent with same-origin requests' => [
            ['test=b; path=/; domain=.wpt.test'],
            'http://wpt.test/cookies/domain',
            'test=b',
        ];
        yield 'domain with leading dot matches host => sent with subdomain requests' => [
            ['test=b; path=/; domain=.wpt.test'],
            'http://sub.wpt.test/cookies/domain',
            'test=b',
        ];
        yield 'No domain attribute => sent with same-origin requests.' => [
            ['test=b; path=/'],
            'http://wpt.test/cookies/domain',
            'test=b',
        ];
        yield 'No domain attribute => not sent with subdomain requests.' => [
            ['test=b; path=/'],
            'http://sub.wpt.test/cookies/domain',
            '',
        ];
    }

    #[DataProvider('idnDomainProvider')]
    public function testIdnDomain(string $uri, array $setCookie, string $expected): void
    {
        $jar = new CookieJar();
        $uri = Uri::of($uri);
        $method = HttpMethod::Get;
        $jar->updateFromGenericResponse($method, $uri, 200, $setCookie);
        $result = $jar->retrieveForGenericRequest($method, $uri);
        Assert::assertSame($expected, $result);
    }

    public static function idnDomainProvider(): iterable
    {
        yield 'UTF8-encoded IDN in domain attribute' => [
            'http://élève.test',
            ['utf8=1; domain=élève.test'],
            '',
        ];
        yield 'UTF8-encoded IDN with non-ASCII dot in domain attribute' => [
            'http://élève.test',
            ['utf8=1; domain=élève。test'],
            '',
        ];
        yield 'wrong UTF8-encoded IDN in domain attribute' => [
            'http://élève.test',
            ['utf8=1; domain=ÿlève.test'],
            '',
        ];
        yield 'punycode IDN in domain attribute' => [
            'http://élève.test',
            ['punycode=1; domain=xn--lve-6lad.test'],
            'punycode=1',
        ];
        yield 'wrong punycode IDN in domain attribute' => [
            'http://élève.test',
            // ÿlève.test
            ['punycode=1; domain=xn--lve-6la7i.test'],
            '',
        ];
        yield 'IDN with invalid UTF-8 bytes in domain attribute' => [
            'http://élève.test',
            ["invalid=1; domain=élève\xff.test"],
            '',
        ];
    }
}
