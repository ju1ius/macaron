<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\Clock;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Souplette\Macaron\Clock\UTCClock;

final class DefaultClockTest extends TestCase
{
    public function testNow(): void
    {
        $tz = new \DateTimeZone('UTC');
        $now = new \DateTimeImmutable('now', $tz);
        $clockNow = (new UTCClock())->now();
        Assert::assertSame($now->getTimestamp(), $clockNow->getTimestamp());
        Assert::assertEquals($tz, $clockNow->getTimezone());
    }
}
