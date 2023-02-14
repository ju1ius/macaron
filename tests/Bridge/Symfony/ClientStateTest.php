<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\Bridge\Symfony;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Souplette\Macaron\Bridge\Symfony\ClientState;
use Souplette\Macaron\Http\HttpMethod;

final class ClientStateTest extends TestCase
{
    private function createState(array $options): ClientState
    {
        return new ClientState($options);
    }

    #[DataProvider('redirectOptionsProvider')]
    public function testRedirectOptions(array $options, bool $sameOrigin, array $expected): void
    {
        $state = $this->createState($options);
        [, $options] = $state->redirect(HttpMethod::Get, 307, $sameOrigin);
        Assert::assertSame($expected, $options);
    }

    public static function redirectOptionsProvider(): iterable
    {
        $opts = [
            'auth_basic' => 'abcdef',
            'auth_bearer' => 'deadbeef',
            'foo' => 'bar',
            'headers' => [
                0 => 'AuthoriZation: beep:boop',
                1 => 'Foo: bar',
                'Authorization' => 'blah:blah',
                'Foo' => 'bar',
            ],
        ];
        $filtered = [
            'foo' => 'bar',
            'headers' => [
                1 => 'Foo: bar',
                'Foo' => 'bar',
            ],

        ];
        yield 'same-origin' => [
            $opts, true, $opts,
        ];
        yield 'cross-origin' => [
            $opts, false, $filtered,
        ];
    }

    #[DataProvider('redirectMethodProvider')]
    public function testRedirectMethod(HttpMethod $method, int $status, HttpMethod $expected): void
    {
        $state = $this->createState([]);
        [$method, ] = $state->redirect($method, $status, true);
        Assert::assertSame($expected, $method);
    }

    public static function redirectMethodProvider(): iterable
    {
        foreach ([301, 302, 303] as $code) {
            yield "HEAD -> {$code} -> HEAD" => [HttpMethod::Head, $code, HttpMethod::Head];
            yield "GET -> {$code} -> GET" => [HttpMethod::Get, $code, HttpMethod::Get];
            yield "POST -> {$code} -> GET" => [HttpMethod::Post, $code, HttpMethod::Get];
        }
        yield "POST -> 307 -> POST" => [HttpMethod::Post, 307, HttpMethod::Post];
        yield "POST -> 308 -> POST" => [HttpMethod::Post, 308, HttpMethod::Post];
    }
}
