<?php declare(strict_types=1);

namespace Souplette\Macaron\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class UTCClock implements ClockInterface
{
    private static \DateTimeZone $timeZone;

    public function __construct()
    {
        self::$timeZone ??= new \DateTimeZone('UTC');
    }

    /**
     * This method should only be used to provide sensible default values.
     * Prefer using the `now()` method via dependency injection.
     */
    public static function utcNow(): DateTimeImmutable
    {
        return new DateTimeImmutable('', self::$timeZone ??= new \DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('', self::$timeZone);
    }
}
