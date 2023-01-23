<?php declare(strict_types=1);

namespace ju1ius\Macaron\Cookie;

use ju1ius\Macaron\Http\HttpMethod;
use Psr\Http\Message\UriInterface;

final class Retrieval
{
    private function __construct(
        public readonly bool $isHttp,
        public readonly UriInterface $uri,
        public readonly bool $isSameSite,
        public readonly bool $isSecure,
        public readonly bool $isRequestMethodSafe,
    ) {
    }

    public static function forHttpRequest(HttpMethod $method, UriInterface $uri, bool $sameSite, bool $secure, bool $safe): self
    {
        return new self(
            true,
            $uri,
            $sameSite,
            $secure,
            $safe,
        );
    }

    public static function forNonHttpRequest(UriInterface $uri): self
    {
        return new self(
            false,
            $uri,
            true,
            true,
            false,
        );
    }
}
