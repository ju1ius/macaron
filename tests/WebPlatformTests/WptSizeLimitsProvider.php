<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\WebPlatformTests;

final class WptSizeLimitsProvider
{
    public static function nameAndValues(): iterable
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

    public static function attributes(): iterable
    {
        $s1024 = str_repeat('e', 1024);
        yield new HttpCookieTestDTO(
            'Too long path attribute (>1024 bytes) is ignored; previous valid path wins.',
            'test=1; path=/cookies/size; path=/cookies/siz' . $s1024,
            'test=1',
            false,
        );
        yield new HttpCookieTestDTO(
            'Too long path attribute (>1024 bytes) is ignored; next valid path wins.',
            "test=2; path=/cookies/siz{$s1024}; path=/cookies/size",
            'test=2',
            false,
        );
        // Look for the cookie using the default path to ensure that it
        // doesn't show up if the path attribute actually takes effect.
        $s1023 = str_repeat('a', 1023);
        yield new HttpCookieTestDTO(
            'Max size path attribute (1024 bytes) is not ignored',
            "test=3; path=/{$s1023};",
            '',
        );
        // Look for the cookie using the default path to ensure that it
        // shows up if the path is ignored.
        yield new HttpCookieTestDTO(
            'Too long path attribute (>1024 bytes) is ignored',
            "test=4; path=/{$s1024};",
            'test=4',
        );
        // This page opens on the www subdomain, so we set domain to {{host}}
        // to see if anything works as expected. Using a valid domain other
        // than ${host} will cause the cookie to fail to be set.

        // NOTE: the domain we use for testing here is technically invalid per
        // the RFCs that define the format of domain names, but currently
        // neither RFC6265bis or the major browsers enforce those restrictions
        // when parsing cookie domain attributes. If that changes, update these
        // tests.
        yield new HttpCookieTestDTO(
            'Too long domain attribute (>1024 bytes) is ignored; previous valid domain wins.',
            "test=5; domain=wpt.test; domain={$s1024}.com;",
            'test=5',
        );
        yield new HttpCookieTestDTO(
            'Too long domain attribute (>1024 bytes) is ignored; next valid domain wins.',
            "test=6; domain={$s1024}.com; domain=wpt.test;",
            'test=6',
        );
        $s1020 = str_repeat('a', 1020);
        yield new HttpCookieTestDTO(
            'Max size domain attribute (1024 bytes) is not ignored',
            "test=7; domain={$s1020}.com;",
            '',
        );
        $s1021 = str_repeat('a', 1021);
        yield new HttpCookieTestDTO(
            'Too long domain attribute (>1024 bytes) is ignored',
            "test=8; domain={$s1021}.com;",
            'test=8',
        );
        $s4096 = self::cookieWithNameAndValueLengths(2048, 2048);
        yield new HttpCookieTestDTO(
            'Set cookie with max size name/value pair and max size attribute value',
            "{$s4096}; domain={$s1020}.com; domain=wpt.test",
            $s4096,
        );
        // RFC6265bis doesn't specify a maximum size of the entire Set-Cookie
        // header, although some browsers do
        $d4 = str_repeat("; domain={$s1020}.com", 4);
        yield new HttpCookieTestDTO(
            'Set cookie with max size name/value pair and multiple max size attributes (>8k bytes total)',
            "{$s4096}{$d4}; domain=wpt.test",
            $s4096,
        );
        $v1024 = str_repeat('1', 1024);
        yield new HttpCookieTestDTO(
            "Max length Max-Age attribute value (1024 bytes) doesn't cause cookie rejection",
            "test=11; max-age={$v1024};",
            'test=11',
        );
        $v1025 = str_repeat('1', 1025);
        yield new HttpCookieTestDTO(
            "Too long Max-Age attribute value (>1024 bytes) doesn't cause cookie rejection",
            "test=12; max-age={$v1025};",
            'test=12',
        );
        $v1023 = str_repeat('1', 1023);
        yield new HttpCookieTestDTO(
            "Max length negative Max-Age attribute value (1024 bytes) doesn't get ignored",
            "test=13; max-age=-{$v1023};",
            '',
        );
        yield new HttpCookieTestDTO(
            'Too long negative Max-Age attribute value (>1024 bytes) gets ignored',
            "test=14; max-age=-{$v1024};",
            'test=14',
        );
    }

    private static function cookieWithNameAndValueLengths(int $name, int $value): string
    {
        $name = str_repeat('t', $name);
        $value = str_repeat('1', $value);
        return "{$name}={$value}";
    }
}
