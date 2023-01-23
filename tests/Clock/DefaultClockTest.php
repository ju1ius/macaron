<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Clock;

use ju1ius\Macaron\Clock\UTCClock;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

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
