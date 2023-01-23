<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\CookieJar;

use ju1ius\Macaron\Cookie;
use ju1ius\Macaron\CookieJar;
use ju1ius\Macaron\Storage\PersistentStorageInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

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
