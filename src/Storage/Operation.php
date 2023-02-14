<?php declare(strict_types=1);

namespace Souplette\Macaron\Storage;

use Souplette\Macaron\Cookie;

final class Operation
{
    public function __construct(
        public readonly OperationType $type,
        public readonly Cookie $cookie,
    ) {
    }
}
