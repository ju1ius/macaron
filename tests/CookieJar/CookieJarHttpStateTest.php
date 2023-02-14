<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\CookieJar;

use ju1ius\Macaron\CookieJar;
use ju1ius\Macaron\Http\HttpMethod;
use ju1ius\Macaron\Http\RequestChain;
use ju1ius\Macaron\Tests\WebPlatformTests\HttpState\HttpStateTestDto;
use ju1ius\Macaron\Tests\WebPlatformTests\HttpState\HttpStateTestProvider;
use ju1ius\Macaron\Uri\Uri;
use ju1ius\Macaron\Uri\UriService;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CookieJarHttpStateTest extends TestCase
{
    #[DataProvider('httpStateProvider')]
    public function testHttpState(HttpStateTestDto $dto): void
    {
        if ($reason = $dto->skip) {
            self::markTestSkipped($reason);
        }

        $jar = new CookieJar($us = new UriService());
        $chain = new RequestChain($us);
        $uri = Uri::of($dto->uri);
        $chain->start($uri);
        $method = HttpMethod::Get;
        $jar->updateFromGenericResponse($method, $uri, 302, $dto->setCookie, $chain->isSameSite());
        $location = Uri::of($dto->redirectUri);
        $chain->next($location);
        $result = $jar->retrieveForGenericRequest($method, $location, $chain->isSameSite());
        Assert::assertSame($dto->expected, $result);
    }

    public static function httpStateProvider(): iterable
    {
        foreach (HttpStateTestProvider::provideTestCases() as $id => $test) {
            if ($test->id === 'disabled-path0029') {
                $test->skip = 'Requires percent-decoding the request URL path.';
            }
            yield $id => [$test];
        }
    }
}
