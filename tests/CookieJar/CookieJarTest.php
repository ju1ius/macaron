<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\CookieJar;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Souplette\Macaron\Cookie;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Uri\UriService;
use Symfony\Component\Clock\MockClock;

final class CookieJarTest extends TestCase
{
    private static function createClock(): ClockInterface
    {
        static $clock;
        return $clock ??= new MockClock('now', 'UTC');
    }

    /**
     * @param Cookie[] $cookies
     */
    private static function createJar(array $cookies = []): CookieJar
    {
        $jar = new CookieJar(new UriService(), clock: self::createClock());
        foreach ($cookies as $cookie) {
            $jar->store($cookie);
        }
        return $jar;
    }

    public function testJarIsInitiallyEmpty(): void
    {
        $jar = self::createJar();
        Assert::assertTrue($jar->isEmpty());
        Assert::assertEmpty($jar->all());
    }

    public function testIsEmpty(): void
    {
        $jar = self::createJar([
            $cookie = new Cookie('foo', 'bar'),
        ]);
        Assert::assertFalse($jar->isEmpty());
        $jar->remove($cookie);
        Assert::assertTrue($jar->isEmpty());
    }

    #[DataProvider('storeProvider')]
    public function testStore(array $cookies, array $expected): void
    {
        $jar = self::createJar($cookies);
        Assert::assertEquals($expected, $jar->all());
    }

    public static function storeProvider(): iterable
    {
        $clock = self::createClock();
        $now = $clock->now();
        $expires = $now->getTimestamp() + 3600;
        yield 'it stores cookies' => [
            $input = [
                new Cookie('foo', 'bar', expiresAt: $expires, createdAt: $now),
                new Cookie('bar', 'baz', expiresAt: $expires, createdAt: $now),
            ],
            $input,
        ];
        yield 'it retains initial creation time when replacing a cookie' => [
            [
                new Cookie('a', 'b', expiresAt: $expires, createdAt: $now),
                new Cookie('a', 'b', expiresAt: $expires, createdAt: $now->modify('+ 1 day')),
            ],
            [
                new Cookie('a', 'b', expiresAt: $expires, createdAt: $now, accessedAt: $now->modify('+1day')),
            ]
        ];
    }

    public function testClear(): void
    {
        $clock = self::createClock();
        $jar = self::createJar([
            new Cookie('a', 'b', createdAt: $clock->now()),
            new Cookie('c', 'd', createdAt: $clock->now()),
        ]);
        Assert::assertNotEmpty($jar->all());
        $jar->clear();
        Assert::assertEmpty($jar->all());
    }

    public function testClearSession(): void
    {
        $clock = self::createClock();
        $jar = self::createJar($input = [
            new Cookie('a', 'b', persistent: true, expiresAt: \PHP_INT_MAX, createdAt: $clock->now()),
            new Cookie('c', 'd', createdAt: $clock->now()),
        ]);
        Assert::assertNotEmpty($jar->all());
        $jar->clearSession();
        Assert::assertEquals(array_slice($input, 0, 1), $jar->all());
    }

    public function testClearExpired(): void
    {
        $clock = self::createClock();
        $now = $clock->now();
        $jar = self::createJar($input = [
            new Cookie('a', 'b', persistent: true, expiresAt: \PHP_INT_MAX, createdAt: $now),
            new Cookie('c', 'd', persistent: false, expiresAt: $now->getTimestamp(), createdAt: $now),
        ]);
        Assert::assertNotEmpty($jar->all());
        $jar->clearExpired();
        Assert::assertEquals(array_slice($input, 0, 1), $jar->all());
    }
}
