<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests;

use ju1ius\Macaron\Internal\ClientState;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class ClientStateTest extends TestCase
{
    /**
     * @dataProvider redirectOptionsProvider
     */
    public function testRedirectOptions(array $options, string $uri, array $expected): void
    {
        $state = new ClientState('https://example.test', $options);
        [, $options] = $state->redirect('GET', 307, $uri);
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
        yield 'matching authority' => [
            $opts, 'https://example.test/foo/bar', $opts,
        ];
        yield 'different host' => [
            $opts, 'https://other.test/foo/bar', $filtered,
        ];
        yield 'different port' => [
            $opts, 'https://example.test:3333/foo/bar', $filtered,
        ];
    }

    /**
     * @dataProvider invalidAuthorityProvider
     */
    public function testConstructorWithInvalidAuthority(string $uri): void
    {
        $this->expectException(TransportExceptionInterface::class);
        new ClientState($uri, []);
    }

    /**
     * @dataProvider invalidAuthorityProvider
     */
    public function testRedirectWithInvalidAuthority(string $uri): void
    {
        $options = new ClientState('https://example.test', []);
        $this->expectException(TransportExceptionInterface::class);
        $options->redirect('GET', 307, $uri);
    }

    public static function invalidAuthorityProvider(): iterable
    {
        yield 'no authority' => ['/foo'];
        yield 'port-only' => [':3333'];
    }
}
