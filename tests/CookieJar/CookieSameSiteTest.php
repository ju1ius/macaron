<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\CookieJar;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Http\HttpMethod;
use Souplette\Macaron\Http\RequestChain;
use Souplette\Macaron\Uri\Uri;
use Souplette\Macaron\Uri\UriService;

final class CookieSameSiteTest extends TestCase
{
    #[DataProvider('sameSiteProvider')]
    public function testSameSite(string $origin, array $redirects, string $target, string $expected): void
    {
        $origin = Uri::of($origin);
        $jar = new CookieJar($us = new UriService());
        $chain = new RequestChain($us);
        $chain->start($origin);
        $setCookie = self::getSameSiteCookies($origin->getHost());
        $method = HttpMethod::Get;
        $jar->updateFromGenericResponse($method, $origin, 200, $setCookie, $chain->isSameSite());
        foreach ($redirects as $location) {
            $chain->next(Uri::of($location));
        }
        $chain->next($target = Uri::of($target));
        $result = $jar->retrieveForGenericRequest($method, $target, $chain->isSameSite());
        Assert::assertSame($expected, $result);
    }

    public static function sameSiteProvider(): iterable
    {
        $strict = 'strict=1; lax=1; none=1; default=1';
        $lax = 'lax=1; none=1; default=1';
        $cross = '';
        yield 'SameSite=None is not sent to non-secure protocol' => [
            'http://wpt.test',
            [],
            'http://wpt.test',
            'strict=1; lax=1; default=1',
        ];
        yield 'Same-host fetches are strictly same-site' => [
            'https://wpt.test',
            [],
            'https://wpt.test',
            $strict,
        ];
        yield 'Subdomain fetches are strictly same-site' => [
            'https://wpt.test',
            [],
            'https://sub.wpt.test',
            $strict,
        ];
        yield 'Cross-site fetches are cross-site' => [
            'https://wpt.test',
            [],
            'https://other.test',
            $cross,
        ];
        yield 'Same-host redirects are strictly same-site' => [
            'https://wpt.test/a',
            ['https://wpt.test/b'],
            'https://wpt.test/c',
            $strict,
        ];
        yield 'Subdomain redirects are strictly same-site' => [
            'https://wpt.test/a',
            ['https://sub.wpt.test/b'],
            'https://wpt.test/c',
            $strict,
        ];
        yield 'Cross-site redirects exclude SameSite=Strict' => [
            'https://wpt.test',
            ['https://other.test'],
            'https://wpt.test',
            $lax,
        ];
    }

    private static function getSameSiteCookies(string $domain): array
    {
        return [
            "strict=1; SameSite=Strict; path=/; domain={$domain}",
            "lax=1; SameSite=Lax; path=/; domain={$domain}",
            "none=1; SameSite=None; Secure; path=/; domain={$domain}",
            "none_insecure=1; SameSite=None; path=/; domain={$domain}",
            "default=1; path=/; domain={$domain}",
        ];
    }
}
