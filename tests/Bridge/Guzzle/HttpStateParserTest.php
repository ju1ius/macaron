<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\Bridge\Guzzle;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Souplette\Macaron\Bridge\Guzzle\MacaronMiddleware;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Tests\WebPlatformTests\HttpState\HttpStateTestDto;
use Souplette\Macaron\Tests\WebPlatformTests\HttpState\HttpStateTestProvider;
use Souplette\Macaron\Uri\UriService;

final class HttpStateParserTest extends TestCase
{
    #[DataProvider('provideHttpState')]
    public function testHttpState(HttpStateTestDto $test): void
    {
        if ($reason = $test->skip) {
            self::markTestSkipped($reason);
        }
        $stack = new HandlerStack($this->createHandler($test));
        $stack->push(self::createMiddleware(), 'macaron');
        $stack->push(Middleware::mapRequest(function(RequestInterface $request) use($test) {
            $uri = $request->getUri();
            if (str_starts_with($uri->getPath(), '/cookie-parser-result')) {
                Assert::assertSame($test->expected, $request->getHeaderLine('cookie'));
            }
            return $request;
        }));
        $handler = $stack->resolve();

        $request = new Request('GET', $test->uri);
        $response = $handler($request, [])->wait();
        Assert::assertSame(302, $response->getStatusCode());

        $request = new Request('GET', $test->redirectUri);
        $response = $handler($request, [])->wait();
        Assert::assertSame(200, $response->getStatusCode());
    }

    public static function provideHttpState(): iterable
    {
        foreach (HttpStateTestProvider::provideTestCases() as $name => $test) {
            if ($test->id === 'disabled-path0029') {
                $test->skip = 'Requires percent-decoding the request URL path.';
            }
            yield $name => [$test];
        }
    }

    private static function createMiddleware(): callable
    {
        $us = new UriService();
        $jar = new CookieJar($us);
        return MacaronMiddleware::create($jar, $us);
    }

    private function createHandler(HttpStateTestDto $test): MockHandler
    {
        return new MockHandler([
            $this->mockResponse(302, [
                'location' => $test->redirectUri,
                'set-cookie' => $test->setCookie,
            ]),
            $this->mockResponse(200, []),
        ]);
    }

    private function mockResponse(int $statusCode, array $headers): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getHeader')
            ->willReturnCallback(function($name) use ($headers) {
                $value = $headers[$name] ?? [];
                return \is_array($value) ? $value : [$value];
            });
        $response->method('getHeaderLine')
            ->willReturnCallback(function ($name) use ($headers) {
                $value = $headers[$name] ?? '';
                return \is_array($value) ? implode(',', $value) : $value;
            });

        return $response;
    }
}
