<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\CookieJar;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Souplette\Macaron\Cookie;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Storage\PersistentStorageInterface;

final class PersistenceTest extends TestCase
{
    public function testSetPersistSessionCookies(): void
    {
        $storage = $this->createMock(PersistentStorageInterface::class);
        $storage->expects($this->once())
            ->method('setPersistSessionCookies')
            ->with(true);
        $jar = new CookieJar(persistentStorage: $storage);
        $jar->setPersistSessionCookies(true);
    }

    public function testPersistentStorageLoadIsLoadedOnce(): void
    {
        $cookies = [
            new Cookie('a', 'a'),
        ];
        $storage = $this->createMock(PersistentStorageInterface::class);
        $storage->expects($this->once())
            ->method('load')
            ->willReturn($cookies);
        $jar = new CookieJar(persistentStorage: $storage);

        $result = $jar->all();
        Assert::assertEquals($cookies, $result);
        $result = $jar->all();
        Assert::assertEquals($cookies, $result);
    }
}
