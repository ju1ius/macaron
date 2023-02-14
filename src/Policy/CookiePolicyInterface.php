<?php declare(strict_types=1);

namespace Souplette\Macaron\Policy;

use Psr\Http\Message\UriInterface;
use Souplette\Macaron\Cookie;
use Souplette\Macaron\Http\HttpMethod;

interface CookiePolicyInterface
{
    /**
     * Default Max-Age upper limit is 400 days.
     *
     * @link https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html#name-the-max-age-attribute
     */
    const RECOMMENDED_MAX_EXPIRY = 400 * 24 * 3600;

    /**
     * Default set of schemes that can handle cookies.
     */
    const DEFAULT_COOKIE_SCHEMES = ['http', 'https', 'ws', 'wss'];

    /**
     * Default set of secure schemes that can handle cookies.
     */
    const DEFAULT_SECURE_SCHEMES = ['https', 'wss'];

    public function getMaxExpiry(): int;

    public function getMaxCount(): int;

    public function getMaxCountPerDomain(): int;

    public function isAllowingPublicSuffixes(): bool;

    public function isRequestSecure(UriInterface $requestUri): bool;

    public function isRequestMethodSafe(HttpMethod $requestMethod, UriInterface $requestUri): bool;

    /**
     * Returns whether cookies should be sent with this request.
     */
    public function shouldSendRequest(HttpMethod $requestMethod, UriInterface $requestUri): bool;

    /**
     * Returns whether the cookie should be sent (included in the cookie header) with this request.
     */
    public function shouldSendCookie(Cookie $cookie, HttpMethod $requestMethod, UriInterface $requestUri): bool;

    /**
     * Returns whether the set-cookie headers returned in the response should be accepted or ignored.
     */
    public function shouldAcceptResponse(HttpMethod $requestMethod, UriInterface $requestUri, int $statusCode): bool;

    /**
     * Returns whether the cookie in the response should be accepted.
     */
    public function shouldAcceptCookie(
        Cookie $cookie,
        HttpMethod $requestMethod,
        UriInterface $requestUri,
        int $statusCode
    ): bool;
}
