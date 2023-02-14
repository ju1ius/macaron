<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\WebPlatformTests;

use Souplette\Macaron\Tests\ResourceHelper;

final class WptProvider
{
    public static function names(): iterable
    {
        yield from self::asProvider(self::load('name.json'));
        $names = [
            'a', '1', '$', '!a', '@a', '#a', '$a', '%a',
            '^a', '&a', '*a', '(a', ')a', '-a', '_a', '+',
            '"a', '"a=b"'
        ];
        foreach ($names as $name) {
            $key = "Name is set as expected for {$name}=test";
            $input = "{$name}=test";
            yield $key => [
                new HttpCookieTestDTO($key, $input, $input),
            ];
        }
        // Tests for control characters (CTLs) in a cookie's name.
        // CTLs are defined by RFC 5234 to be %x00-1F / %x7F.
        // All CTLs, except %x09 (the tab character),
        // should cause the cookie to be rejected.
        foreach (self::controlChars() as $b => $c) {
            $hex = sprintf('\x%02X', $b);
            $input = "test{$b}{$c}name={$b}";
            $expected = '';
            if ($b === 0x09) {
                $key = "Cookie with {$hex} in name is accepted.";
                $expected = $input;
            } else {
                $key = "Cookie with {$hex} in name is rejected.";
            }
            yield $key => [
                new HttpCookieTestDTO($key, $input, $expected),
            ];
        }
    }

    public static function values(): iterable
    {
        foreach (self::load('value.json') as $key => $dto) {
            $dto->skip = match ($dto->name) {
                'Set cookie but ignore value after LF' => 'This test doesnt seem to comply with RFC6265bis...',
                default => null,
            };
            yield $key => [$dto];
        }
        foreach (self::controlChars() as $b => $c) {
            $hex = sprintf('\x%02X', $b);
            $input = "test={$b}{$c}value";
            $expected = '';
            if ($b === 0x09) {
                $key = "Cookie with {$hex} in value is accepted.";
                $expected = $input;
            } else {
                $key = "Cookie with {$hex} in value is rejected.";
            }
            yield $key => [
                new HttpCookieTestDTO($key, $input, $expected),
            ];
        }
    }

    public static function encoding(): iterable
    {
        yield from self::asProvider(self::load('encoding.json'));
    }

    public static function paths(): iterable
    {
        foreach (ResourceHelper::json("wpt/path.json") as $test) {
            ['name' => $name, 'path' => $path] = $test;
            $shouldMatch = $test['match'] ?? true;
            $key = sprintf(
                'Set-Cookie on /cookies/resources/echo-cookie.html %s "%s" cookie with path: %s',
                $shouldMatch ? 'sets' : 'does not set',
                $name,
                $path,
            );
            $input = "{$name}=1; Path={$path}";
            $expected = match ($shouldMatch) {
                true => "{$name}=1",
                false => '',
            };
            yield $key => [
                new HttpCookieTestDTO($key, $input, $expected),
            ];
        }
    }

    public static function ordering(): iterable
    {
        yield from self::asProvider(self::load('ordering.json', HttpCookieRedirectTestDTO::class));
    }

    public static function sizes(): iterable
    {
        yield from self::asProvider(WptSizeLimitsProvider::nameAndValues());
        yield from self::asProvider(WptSizeLimitsProvider::attributes());
    }

    public static function nameAndValueSize(): iterable
    {
        $limit1 = self::cookieWithNameAndValueLengths(2048, 2048);
        yield new HttpCookieTestDTO(
            "Set max-size cookie with largest possible name and value (4096 bytes)",
            $limit1,
            $limit1,
        );
        yield new HttpCookieTestDTO(
            "Ignore cookie with name larger than 4096 and 1 byte value",
            self::cookieWithNameAndValueLengths(4097, 1),
            "",
        );
        yield new HttpCookieTestDTO(
            "Set max-size value-less cookie",
            $limit2 = self::cookieWithNameAndValueLengths(4096, 0),
            $limit2,
        );
        yield new HttpCookieTestDTO(
            "Ignore value-less cookie with name larger than 4096 bytes",
            self::cookieWithNameAndValueLengths(4097, 0),
            "",
        );
        yield new HttpCookieTestDTO(
            "Set max-size cookie with largest possible value (4095 bytes)",
            $limit3 = self::cookieWithNameAndValueLengths(1, 4095),
            $limit3,
        );
        yield new HttpCookieTestDTO(
            "Ignore named cookie (with non-zero length) and value larger than 4095 bytes",
            self::cookieWithNameAndValueLengths(1, 4096),
            "",
        );
        yield new HttpCookieTestDTO(
            "Ignore named cookie with length larger than 4095 bytes, and a non-zero value",
            self::cookieWithNameAndValueLengths(4096, 1),
            "",
        );
        yield new HttpCookieTestDTO(
            "Set max-size name-less cookie",
            $limit4 = self::cookieWithNameAndValueLengths(0, 4096), // it won't come back with leading =
            substr($limit4, 1),
        );
        yield new HttpCookieTestDTO(
            "Ignore name-less cookie with value larger than 4096 bytes",
            $limit5 = self::cookieWithNameAndValueLengths(0, 4097),
            "",
        );
        yield new HttpCookieTestDTO(
            "Ignore name-less cookie (without leading =) with value larger than 4096 bytes", // slice off leading =
            substr($limit5, 1),
            "",
        );
        yield new HttpCookieTestDTO(
            "Set max-size cookie that also has an attribute",
            $limit1 . '; Max-Age:43110;',
            $limit1,
        );
    }

    private static function cookieWithNameAndValueLengths(int $name, int $value): string
    {
        $name = str_repeat('t', $name);
        $value = str_repeat('1', $value);
        return "{$name}={$value}";
    }

    private static function asProvider(iterable $it): iterable
    {
        foreach ($it as $key => $value) {
            if (!\is_string($key)) {
                $key = (string)$value;
            }
            yield $key => [$value];
        }
    }

    /**
     * @param string $path
     * @param string $class
     * @return iterable<string, HttpCookieTestDTO>
     * @throws \JsonException
     */
    private static function load(string $path, string $class = HttpCookieTestDTO::class): iterable
    {
        foreach (ResourceHelper::json("wpt/{$path}") as $i => $test) {
            $dto = $class::fromJson($test);
            yield "#{$i} {$dto}" => $dto;
        }
    }

    private static function controlChars(): array
    {
        $chars = [];
        foreach (range(0x00, 0x1F) as $b) {
            $chars[$b] = \chr($b);
        }
        $chars[0x7F] = \chr(0x7F);
        return $chars;
    }
}
