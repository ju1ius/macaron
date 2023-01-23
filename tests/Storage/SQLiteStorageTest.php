<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Storage;

use ju1ius\Macaron\Cookie;
use ju1ius\Macaron\Exception\CookieStorageException;
use ju1ius\Macaron\Storage\SQLiteStorage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

final class SQLiteStorageTest extends TestCase
{
    private static function getClock(): ClockInterface
    {
        static $clock;
        return $clock ??= new MockClock('now', 'UTC');
    }

    private static function getNow(): \DateTimeImmutable
    {
        $now = self::getClock()->now();
        return $now->setTimestamp($now->getTimestamp());
    }

    private static function createCookie(...$args): Cookie
    {
        $now = self::getNow();
        return new Cookie(...$args, createdAt: $now);
    }

    public function testStorageIsInitiallyEmpty(): void
    {
        $storage = new SQLiteStorage();
        Assert::assertEmpty($storage->load());
    }

    public function testStorageDoesPersistUnlessFlushed(): void
    {
        $storage = new SQLiteStorage(persistSessionCookies: true);
        $storage->add($a = self::createCookie('a', 'b', path: ''));
        $storage->add($b = self::createCookie('b', 'c', path: ''));

        Assert::assertEmpty($storage->load());
        $storage->flush();
        Assert::assertEquals([$a, $b], $storage->load());
    }

    public function testStorageFlushesWhenQueueIsFull(): void
    {
        $storage = new SQLiteStorage(maxPendingTasks: 2);
        $storage->add($a = self::createCookie('a', 'b', path: '', persistent: true));
        $storage->add($b = self::createCookie('b', 'c', path: '', persistent: true));
        // no flush!
        Assert::assertEquals([$a, $b], $storage->load());
    }

    public static function testLoadDomains(): void
    {
        $storage = new SQLiteStorage(persistSessionCookies: true);
        $storage->add($a = self::createCookie('a', 'b', domain: 'a.com', path: '/'));
        $storage->add($b = self::createCookie('b', 'c', domain: 'b.com', path: '/'));
        $storage->flush();
        Assert::assertEquals([$a], $storage->loadDomains('a.com'));
        Assert::assertEquals([$b], $storage->loadDomains('b.com'));
    }

    public function testSessionCookiesAreNotPersistedByDefault(): void
    {
        $storage = new SQLiteStorage();
        $storage->add(new Cookie('a', 'b', path: ''));
        $storage->add(new Cookie('b', 'c', path: ''));
        $storage->flush();
        Assert::assertEmpty($storage->load());
    }

    public static function testSetPersistSessionCookies(): void
    {
        $storage = new SQLiteStorage(persistSessionCookies: false);
        $storage->add($a = self::createCookie('a', 'b', domain: 'a.com', path: '/'));
        $storage->add($b = self::createCookie('b', 'c', domain: 'b.com', path: '/'));
        $storage->flush();
        Assert::assertEmpty($storage->load());
        $storage->setPersistSessionCookies(true);
        $storage->add($a);
        $storage->add($b);
        $storage->flush();
        Assert::assertEquals([$a, $b], $storage->load());
    }

    public function testDelete(): void
    {
        $storage = new SQLiteStorage();
        $storage->add($a = self::createCookie('a', 'b', path: '', persistent: true));
        $storage->add($b = self::createCookie('b', 'c', path: '', persistent: true));
        $storage->flush();
        Assert::assertEquals([$a, $b], $storage->load());
        $storage->delete($a);
        $storage->delete($b);
        $storage->flush();
        Assert::assertEmpty($storage->load());
    }

    public function testTouch(): void
    {
        $storage = new SQLiteStorage();
        $storage->add($a = self::createCookie('a', 'b', path: '', persistent: true));
        $storage->flush();
        Assert::assertEquals([$a], $storage->load());
        $a->accessedAt = $now = self::getNow()->modify('+10 seconds');
        $storage->touch($a);
        $storage->flush();
        $cookies = $storage->load();
        Assert::assertEquals($a, $cookies[0]);
        Assert::assertEquals($now, $cookies[0]->accessedAt);
    }

    public function testClearRemovesAllCookies(): void
    {
        $storage = new SQLiteStorage();
        $storage->add($a = self::createCookie('a', 'b', path: '', persistent: true));
        $storage->add($b = self::createCookie('b', 'c', path: '', persistent: true));
        $storage->flush();
        Assert::assertEquals([$a, $b], $storage->load());
        $storage->clear();
        Assert::assertEmpty($storage->load());
    }

    public function testStorageException(): void
    {
        $storage = new SQLiteStorage();
        // null path will trigger a constraint error
        $storage->add($a = self::createCookie('a', 'b', path: null, persistent: true));
        $this->expectException(CookieStorageException::class);
        $storage->flush();
    }
}
