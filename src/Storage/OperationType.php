<?php declare(strict_types=1);

namespace Souplette\Macaron\Storage;

enum OperationType
{
    case Add;
    case Delete;
    case Touch;
}
