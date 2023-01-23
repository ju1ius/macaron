<?php declare(strict_types=1);

namespace ju1ius\Macaron\Cookie;

enum SameSite: string
{
    case Default = '';
    case None = 'none';
    case Lax = 'lax';
    case Strict = 'strict';
}
