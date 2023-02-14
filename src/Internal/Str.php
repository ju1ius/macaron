<?php declare(strict_types=1);

namespace Souplette\Macaron\Internal;

final class Str
{
    public static function isAscii(string $subject): bool
    {
        return !$subject || mb_check_encoding($subject, 'ASCII');
    }

    public static function iStartsWith(string $subject, string $prefix): bool
    {
        return $prefix === '' || (
            \strlen($subject) >= \strlen($prefix)
            && strncasecmp($subject, $prefix, \strlen($prefix)) === 0
        );
    }
}
