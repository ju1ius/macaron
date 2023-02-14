<?php declare(strict_types=1);

namespace Souplette\Macaron\Policy;

use Psr\Http\Message\UriInterface;
use Souplette\Macaron\Cookie;
use Souplette\Macaron\Http\HttpMethod;

final class DefaultPolicy implements CookiePolicyInterface
{
    public function __construct(
        private readonly int $maxExpiry = self::RECOMMENDED_MAX_EXPIRY,
        private readonly int $maxCookiesPerDomain = \PHP_INT_MAX,
        private readonly int $maxCount = \PHP_INT_MAX,
        private readonly bool $allowPublicSuffixes = false,
        private readonly array $secureSchemes = CookiePolicyInterface::DEFAULT_SECURE_SCHEMES,
    ) {
    }

    public function getMaxExpiry(): int
    {
        return $this->maxExpiry;
    }

    public function getMaxCount(): int
    {
        return $this->maxCount;
    }

    public function getMaxCountPerDomain(): int
    {
        return $this->maxCookiesPerDomain;
    }

    public function isAllowingPublicSuffixes(): bool
    {
        return $this->allowPublicSuffixes;
    }

    public function isRequestSecure(UriInterface $requestUri): bool
    {
        return \in_array($requestUri->getScheme(), $this->secureSchemes, true);
    }

    public function isRequestMethodSafe(HttpMethod $requestMethod, UriInterface $requestUri): bool
    {
        return $requestMethod->isSafe();
    }

    public function shouldSendRequest(HttpMethod $requestMethod, UriInterface $requestUri): bool
    {
        return true;
    }

    public function shouldSendCookie(Cookie $cookie, HttpMethod $requestMethod, UriInterface $requestUri): bool
    {
        return true;
    }

    public function shouldAcceptResponse(HttpMethod $requestMethod, UriInterface $requestUri, int $statusCode): bool
    {
        return true;
    }

    public function shouldAcceptCookie(
        Cookie $cookie,
        HttpMethod $requestMethod,
        UriInterface $requestUri,
        int $statusCode
    ): bool {
        return true;
    }
}
