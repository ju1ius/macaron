<?php declare(strict_types=1);

namespace ju1ius\Macaron\Cookie;

/**
 * Implementation of RFC 6265 tolerant date parsing algorithm.
 *
 * @link https://httpwg.org/specs/rfc6265.html#cookie-date
 * @internal
 */
final class CookieDateParser
{
    private const NON_DELIM_RX = '/[\x00-\x08\x0A-\x1F\d:A-Za-z\x7F-\xFF]+/';
    private const DELIM_RX = '/[\x09\x20-\x2F\x3B-\x40\x5B-\x60\x7B\x7E]+/';
    private const YEAR_RX = '/^(\d{2,4})(?:\D.*)?$/';
    private const MONTH_RX = '/^(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec).*$/i';
    private const DAY_RX = '/^(\d{1,2})(?:\D.*)?$/';
    private const TIME_RX = '/^(\d{1,2}):(\d{1,2}):(\d{1,2})(?:\D.*)?$/';

    public static function parse(string $input): ?\DateTimeImmutable
    {
        if (!$tokens = self::tokenize($input)) {
            return null;
        }

        $foundTime = $foundDayOfMonth = $foundMonth = $foundYear = false;
        $year = $month = $dayOfMonth = $hour = $min = $sec = null;
        foreach ($tokens as $token) {
            if (!$foundTime && preg_match(self::TIME_RX, $token, $m)) {
                $foundTime = true;
                $hour = (int)$m[1];
                $min = (int)$m[2];
                $sec = (int)$m[3];
                continue;
            }
            if (!$foundDayOfMonth && preg_match(self::DAY_RX, $token, $m)) {
                $foundDayOfMonth = true;
                $dayOfMonth = (int)$m[1];
                continue;
            }
            if (!$foundMonth && preg_match(self::MONTH_RX, $token, $m)) {
                $foundMonth = true;
                $month = match (\strtolower($m[1])) {
                    'jan' => 1,
                    'feb' => 2,
                    'mar' => 3,
                    'apr' => 4,
                    'may' => 5,
                    'jun' => 6,
                    'jul' => 7,
                    'aug' => 8,
                    'sep' => 9,
                    'oct' => 10,
                    'nov' => 11,
                    'dec' => 12,
                };
                continue;
            }
            if (!$foundYear && preg_match(self::YEAR_RX, $token, $m)) {
                $foundYear = true;
                $year = (int)$m[1];
                continue;
            }
        }
        if (!$foundDayOfMonth || !$foundMonth || !$foundYear || !$foundTime) {
            return null;
        }

        if ($year >= 70 && $year <= 99) {
            $year += 1900;
        } elseif ($year >= 0 && $year <= 69) {
            $year += 2000;
        }

        if (
            ($dayOfMonth < 1 || $dayOfMonth > 31)
            || $year < 1601
            || $hour > 23
            || $min > 59
            || $sec > 59
        ) {
            return null;
        }

        return (new \DateTimeImmutable())
            ->setTimezone(new \DateTimeZone('UTC'))
            ->setDate($year, $month, $dayOfMonth)
            ->setTime($hour, $min, $sec);
    }

    private static function tokenize(string $input): array
    {
        if (preg_match_all(self::NON_DELIM_RX, $input, $matches)) {
            return $matches[0];
        }
        return [];
    }
}
