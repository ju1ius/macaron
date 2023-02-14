<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\CookieJar;

use GuzzleHttp\Psr7\UriResolver;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Http\HttpMethod;
use Souplette\Macaron\Http\RequestChain;
use Souplette\Macaron\Tests\WebPlatformTests\HttpCookieRedirectTestDTO;
use Souplette\Macaron\Tests\WebPlatformTests\HttpCookieTestDTO;
use Souplette\Macaron\Tests\WebPlatformTests\WptProvider;
use Souplette\Macaron\Uri\Uri;
use Souplette\Macaron\Uri\UriService;

final class CookieJarWptTest extends TestCase
{
    #[DataProviderExternal(WptProvider::class, 'names')]
    public function testCookieName(HttpCookieTestDTO $dto): void
    {
        $this->runHttpCookieTest($dto);
    }

    #[DataProviderExternal(WptProvider::class, 'values')]
    public function testCookieValue(HttpCookieTestDTO $dto): void
    {
        $this->runHttpCookieTest($dto);
    }

    #[DataProviderExternal(WptProvider::class, 'sizes')]
    public function testSizeLimits(HttpCookieTestDTO $dto): void
    {
        $this->runHttpCookieTest($dto, 'http://wpt.test/cookies/size');
    }

    #[DataProviderExternal(WptProvider::class, 'encoding')]
    public function testEncoding(HttpCookieTestDTO $dto): void
    {
        $this->runHttpCookieTest($dto);
    }

    #[DataProviderExternal(WptProvider::class, 'paths')]
    public function testPathMatches(HttpCookieTestDTO $dto): void
    {
        $this->runHttpCookieTest($dto, 'http://wp.test/cookies/path/match.html');
    }

    #[DataProviderExternal(WptProvider::class, 'ordering')]
    public function testOrdering(HttpCookieRedirectTestDTO $dto): void
    {
        $this->runHttpRedirectCookieTest($dto, 'http://wp.test/cookies/path/match.html');
    }

    private function runHttpCookieTest(HttpCookieTestDTO $dto, string $uri = 'http://wpt.test'): void
    {
        if ($reason = $dto->skip) {
            self::markTestSkipped($reason);
        }
        $jar = new CookieJar();
        $uri = Uri::of($uri);
        $method = HttpMethod::Get;
        $jar->updateFromGenericResponse($method, $uri, 200, $dto->setCookie);
        $result = $jar->retrieveForGenericRequest($method, $uri);
        Assert::assertSame($dto->expected, $result);
    }

    private function runHttpRedirectCookieTest(HttpCookieRedirectTestDTO $dto, string $uri = 'http://wpt.test'): void
    {
        $uri = Uri::of($uri);
        $jar = new CookieJar($us = new UriService());
        $chain = new RequestChain($us);
        $chain->start($uri);
        $method = HttpMethod::Get;
        $headers = array_map(
            fn($h) => str_replace('{{host}}', $uri->getHost(), $h),
            $dto->setCookie,
        );
        $jar->updateFromGenericResponse($method, $uri, 200, $headers);

        $location = UriResolver::resolve($uri, Uri::of($dto->location));
        $chain->next($location);
        $result = $jar->retrieveForGenericRequest($method, $location, $chain->isSameSite());
        Assert::assertSame($dto->expected, $result);
    }
}
