<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests;

use JsonException;

final class ResourceHelper
{
    public static function path(string $relPath): string
    {
        return __DIR__ . '/Resources/' . ltrim($relPath, '/');
    }

    /**
     * @return string[]
     */
    public static function glob(string $pattern): array
    {
        return \glob(self::path($pattern));
    }

    /**
     * @throws JsonException
     */
    public static function json(string $path): mixed
    {
        $json = file_get_contents(self::path($path));
        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
