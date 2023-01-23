<?php declare(strict_types=1);

namespace ju1ius\Macaron\Storage;

use ju1ius\Macaron\Cookie;

final class Operation
{
    public function __construct(
        public readonly OperationType $type,
        public readonly Cookie $cookie,
    ) {
    }
}
