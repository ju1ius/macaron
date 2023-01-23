<?php declare(strict_types=1);

namespace ju1ius\Macaron\Cookie;

use ju1ius\Macaron\Exception\MacaronExceptionInterface;

final class ParseError extends \RuntimeException implements MacaronExceptionInterface
{
    public static function emptyHeader(): self
    {
        return new self('Set-Cookie header is empty.');
    }

    public static function invalidCharacters(): self
    {
        return new self('Set-Cookie header contains invalid characters.');
    }

    public static function nameValueLimit(): self
    {
        return new self('Cookie name/value pair exceeds the 4096 octets limit.');
    }
}
