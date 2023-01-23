<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\WebPlatformTests\HttpState;

use ju1ius\Macaron\Tests\ResourceHelper;

final class DateParserProvider
{
    public static function provideTestCases(): iterable
    {
        foreach (self::loadDates() as $i => $test) {
            $input = $test['test'];
            if ($expected = $test['expected']) {
                $expected = new \DateTimeImmutable($expected, new \DateTimeZone('UTC'));
            }
            yield "#{$i}: {$input}" => [$input, $expected];
        }
    }

    private static function loadDates(): iterable
    {
        yield from ResourceHelper::json('http-state/dates.json');
        yield from ResourceHelper::json('http-state/bsd-dates.json');
    }
}
