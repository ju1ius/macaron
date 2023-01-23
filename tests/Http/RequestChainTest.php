<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Http;

use ju1ius\Macaron\Http\RequestChain;
use ju1ius\Macaron\Uri\Uri;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestChainTest extends TestCase
{
    public function testInitialState(): void
    {
        $chain = new RequestChain();
        Assert::assertTrue($chain->isEmpty(), 'Chain is initially empty.');
        Assert::assertTrue($chain->isSameOrigin(), 'Chain is initially same-origin.');
        Assert::assertTrue($chain->isSameSite(), 'Chain is initially same-site.');

        $chain->start(Uri::of('http://foo.bar'));
        Assert::assertFalse($chain->isEmpty(), 'Chain is not empty after start.');
        Assert::assertTrue($chain->isSameOrigin(), 'Chain is same-origin after start.');
        Assert::assertTrue($chain->isSameSite(), 'Chain is same-site after start.');

        $chain->finish();
        Assert::assertTrue($chain->isEmpty(), 'Chain is empty after finish.');
        Assert::assertTrue($chain->isSameOrigin(), 'Chain is same-origin after finish.');
        Assert::assertTrue($chain->isSameSite(), 'Chain is same-site after finish.');
    }

    #[DataProvider('nextProvider')]
    public function testNext(string $origin, array $steps): void
    {
        $chain = new RequestChain();
        $chain->start(Uri::of($origin));
        foreach ($steps as [$uri, $sameOrigin, $sameSite]) {
            $chain->next(Uri::of($uri));
            Assert::assertSame($sameOrigin, $chain->isSameOrigin());
            Assert::assertSame($sameSite, $chain->isSameSite());
        }
    }

    public static function nextProvider(): iterable
    {
        yield 'only path changes' => [
            'http://example.com/foo',
            [
                ['http://example.com/bar', true, true],
            ],
        ];
        yield 'protocol changes' => [
            'http://example.com/foo',
            [
                ['https://example.com/foo', false, false],
            ],
        ];
        yield 'subdomain changes' => [
            'http://example.com/foo',
            [
                ['http://sub.example.com/foo', false, true],
            ],
        ];
        yield 'TLD changes' => [
            'http://example.com/foo',
            [
                ['http://example.org/foo', false, false],
            ],
        ];
        yield 'port changes' => [
            'http://example.com/foo',
            [
                ['http://example.com:8080/foo', false, true],
            ],
        ];
        yield 'handles default port' => [
            'http://example.com/foo',
            [
                ['http://example.com:80/foo', true, true],
            ],
        ];
        yield 'cross-site redirects' => [
            'http://a.com',
            [
                ['http://b.com', false, false],
                ['http://b.com/c', false, false],
                // same-origin is computed from first uri in the chain
                // same-site is computed from every uri in the chain
                ['http://a.com/b', true, false],
            ],
        ];
    }

    public function testAllowCrossSiteSameSite(): void
    {
        $chain = new RequestChain(allowCrossSiteSameSite: true);
        $chain->start(Uri::of('http://a.b'));
        $chain->next(Uri::of('http://b.c'));
        $chain->next(Uri::of('http://sub.a.b'));
        Assert::assertTrue($chain->isSameSite());
    }

    public function testInvalidState(): void
    {
        $chain = new RequestChain();
        $this->expectException(\LogicException::class);
        $chain->next(Uri::of('http://foo.bar'));
    }
}
