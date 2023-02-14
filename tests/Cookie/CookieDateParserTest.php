<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\Cookie;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Souplette\Macaron\Cookie\CookieDateParser;
use Souplette\Macaron\Tests\WebPlatformTests\HttpState\DateParserProvider;

final class CookieDateParserTest extends TestCase
{
    #[DataProvider('provideParseData')]
    public function testParse(string $input, ?\DateTimeImmutable $expected): void
    {
        $result = CookieDateParser::parse($input);
        if (!$expected) {
            Assert::assertNull($result);
        } else {
            Assert::assertEquals($expected, $result);
        }
    }

    public static function provideParseData(): iterable
    {
        yield 'empty string' => ['', null];
        yield 'two-digit years < 70 are in the 21st century' => [
            'jan 1st 22 0:0:0',
            new \DateTimeImmutable('jan 1st 2022 0:0:0.0 UTC'),
        ];
        yield 'two-digit years >= 70 are in the 20st century' => [
            'dec 24 79 0:0:0',
            new \DateTimeImmutable('dec 24 1979 0:0:0.0 UTC'),
        ];
        yield 'invalid day' => ['jan 0 1970 0:0:0', null];
        yield 'invalid day #2' => ['jan 32 1970 0:0:0', null];
        yield 'invalid year' => ['jan 1 1505 0:0:0', null];
        yield 'invalid hours' => ['jan 1 1970 25:0:0', null];
        yield 'invalid minutes' => ['jan 1 1970 0:66:0', null];
        yield 'invalid seconds' => ['jan 1 1970 0:0:66', null];
        yield from self::providePhpDateFormats();
        yield from self::provideMonthNames();
        yield from DateParserProvider::provideTestCases();
    }

    private static function provideMonthNames(): iterable
    {
        $months = [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December',
        ];
        $unix = new \DateTimeImmutable('@0', new \DateTimeZone('UTC'));
        foreach ($months as $i => $month) {
            $short = substr($month, 0, 3);
            $date = $unix->modify($short);
            yield "Handles short month: {$short}" => [
                "{$short} 1st 1970 0:0:0",
                $date,
            ];
            yield "Handles long month: {$month}" => [
                "{$month} 1st 1970 0:0:0",
                $date,
            ];
        }
    }

    private static function providePhpDateFormats(): iterable
    {
        $unix = new \DateTimeImmutable('@0', new \DateTimeZone('UTC'));
        $formats = [
            'ATOM' => false,
            'COOKIE' => true,
            'ISO8601' => false,
            'RFC822' => true,
            'RFC850' => true,
            'RFC1036' => true,
            'RFC1123' => true,
            'RFC7231' => true,
            'RFC2822' => true,
            'RFC3339' => false,
            'RFC3339_EXTENDED' => false,
            'RSS' => true,
            'W3C' => false,
        ];
        if (\PHP_VERSION_ID >= 80200) {
            $formats['ISO8601_EXPANDED'] = false;
        }
        foreach ($formats as $name => $supported) {
            $format = constant("DATE_{$name}");
            $key = sprintf(
                '%s format DATE_%s: %s',
                $supported ? 'Supports' : 'Does not support',
                $name,
                $format,
            );
            yield $key => [
                $unix->format($format),
                $supported ? $unix : null,
            ];
        }
    }
}
