<?php declare(strict_types=1);

namespace ju1ius\Macaron\Uri;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class UriFactory implements UriFactoryInterface
{
    public function createUri(string $uri = ''): UriInterface
    {
        return Uri::of($uri);
    }
}
