<?php declare(strict_types=1);

namespace Souplette\Macaron\Cookie;

use Psr\Http\Message\UriInterface;
use Souplette\Macaron\Cookie;

/**
 * Internal implementation for RFC6265 cookie path algorithms.
 *
 * @link https://httpwg.org/specs/rfc6265.html#cookie-path
 * @link https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html#name-paths-and-path-match
 * @internal
 */
final class CookiePath
{
    public static function default(UriInterface $requestUri): string
    {
        // 1. Let uri-path be the path portion of the request-uri if such a portion exists (and empty otherwise).
        $path = $requestUri->getPath();
        // 2. If the uri-path is empty or if the first character of the uri-path is not "/":
        if ($path === '' || $path[0] !== '/') {
            // output "/" and skip the remaining steps.
            return '/';
        }

        // 3. If the uri-path contains no more than one "/":
        $rightmostSeparatorPosition = strrpos($path, '/');
        if ($rightmostSeparatorPosition === 0) {
            // output "/" and skip the remaining step.
            return '/';
        }

        // 4. Output the characters of the uri-path from the first character up to, but not including,
        // the right-most "/".
        return substr($path, 0, $rightmostSeparatorPosition);
    }

    public static function matches(UriInterface|Cookie $requestPath, string $cookiePath): bool
    {
        if ($requestPath instanceof Cookie) {
            $requestPath = $requestPath->path;
        } else {
            $requestPath = $requestPath->getPath();
        }
        if ($requestPath === '') {
            $requestPath = '/';
        }

        // A request-path path-matches a given cookie-path if at least one of the following conditions holds:
        // 1. The cookie-path and the request-path are identical.
        if ($requestPath === $cookiePath) {
            return true;
        }
        // 2. The cookie-path is a prefix of the request-path, and either:
        if (str_starts_with($requestPath, $cookiePath)) {
            // the last character of the cookie-path is "/"
            if (str_ends_with($cookiePath, '/')) {
                return true;
            }
            // or the first character of the request-path that is not included in the cookie-path is "/"
            if ($requestPath[\strlen($cookiePath)] === '/') {
                return true;
            }
        }

        return false;
    }
}
