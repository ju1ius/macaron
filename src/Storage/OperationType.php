<?php declare(strict_types=1);

namespace ju1ius\Macaron\Storage;

enum OperationType
{
    case Add;
    case Delete;
    case Touch;
}
