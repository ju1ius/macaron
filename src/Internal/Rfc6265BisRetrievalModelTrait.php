<?php declare(strict_types=1);

namespace Souplette\Macaron\Internal;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Souplette\Macaron\Cookie;
use Souplette\Macaron\Cookie\CookiePath;
use Souplette\Macaron\Cookie\Domain;
use Souplette\Macaron\Cookie\Retrieval;
use Souplette\Macaron\Cookie\SameSite;
use Souplette\Macaron\Http\HttpMethod;

/**
 * @link https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html#name-retrieval-algorithm
 */
trait Rfc6265BisRetrievalModelTrait
{
    public function retrieveForRequest(RequestInterface $request, bool $isSameSite = true): ?string
    {
        return $this->retrieveForGenericRequest(
            HttpMethod::of($request->getMethod()),
            $request->getUri(),
            $isSameSite,
        );
    }

    public function retrieveForGenericRequest(
        HttpMethod $requestMethod,
        UriInterface $requestUri,
        bool $sameSite = true
    ): ?string {
        $this->clearExpired();

        if (!$this->policy->shouldSendRequest($requestMethod, $requestUri)) {
            return null;
        }
        $retrieval = Retrieval::forHttpRequest(
            $requestMethod,
            $requestUri,
            $sameSite,
            $this->policy->isRequestSecure($requestUri),
            $this->policy->isRequestMethodSafe($requestMethod, $requestUri),
        );
        // 5.7.3
        // 1. Let cookie-list be the set of cookies from the cookie store that meets all the following requirements:
        $requestDomain = Domain::of($requestUri);
        // Either:
        // * The cookie's host-only-flag is true and the canonicalized host of the retrieval's URI is identical to the cookie's domain.
        // * The cookie's host-only-flag is false and the canonicalized host of the retrieval's URI domain-matches the cookie's domain.
        // NOTE: we split this step between $matchDomain and $matchCookie
        $matchDomain = static function(string $cookieDomain) use ($requestDomain) {
            return $requestDomain->matches(Domain::of($cookieDomain));
        };
        $matchPath = static function(string $cookiePath) use ($requestUri) {
            // The retrieval's URI's path path-matches the cookie's path.
            return CookiePath::matches($requestUri, $cookiePath);
        };
        $matchCookie = function(Cookie $cookie) use ($requestMethod, $requestUri, $retrieval) {
            if ($cookie->hostOnly && $requestUri->getHost() !== $cookie->domain) {
                return false;
            }
            // If the cookie's secure-only-flag is true,
            // then the retrieval's URI's scheme must denote a "secure" protocol (as defined by the user agent).
            if ($cookie->secureOnly && !$retrieval->isSecure) {
                return false;
            }
            // If the cookie's http-only-flag is true, then exclude the cookie if the retrieval's type is "non-HTTP".
            if ($cookie->httpOnly && !$retrieval->isHttp) {
                return false;
            }
            // If the cookie's same-site-flag is not "None" and the retrieval's same-site status is "cross-site",
            if ($cookie->sameSite !== SameSite::None && !$retrieval->isSameSite) {
                // then exclude the cookie unless all the following conditions are met:
                // The retrieval's type is "HTTP".
                // The same-site-flag is "Lax" or "Default".
                // The HTTP request associated with the retrieval uses a "safe" method.
                // The target browsing context of the HTTP request associated with the retrieval is a top-level browsing context.
                if (
                    !$retrieval->isHttp
                    || ($cookie->sameSite !== SameSite::Lax && $cookie->sameSite !== SameSite::Default)
                    || !$retrieval->isRequestMethodSafe
                ) {
                    return false;
                }
            }
            if (!$this->policy->shouldSendCookie($cookie, $requestMethod, $requestUri)) {
                return false;
            }
            return true;
        };
        $cookieList = $this->matching($matchCookie, $matchDomain, $matchPath);
        $cookieList = iterator_to_array($cookieList);
        // 2. The user agent SHOULD sort the cookie-list in the following order:
        usort($cookieList, self::compareCookiesForRetrieval(...));
        // 3. Update the last-access-time of each cookie in the cookie-list to the current date and time.
        $now = $this->clock->now();
        /** @var Cookie[] $cookieList */
        foreach ($cookieList as $cookie) {
            $cookie->touch($now);
            $this->persistentStorage?->touch($cookie);
        }
        $this->persistentStorage?->flush();
        // 4. Serialize the cookie-list into a cookie-string by processing each cookie in the cookie-list in order:
        return self::serializeCookieListForRetrieval($cookieList);
    }

    /**
     * Comparison function for the RFC6265 retrieval algorithm
     * @link https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html#section-5.7.3
     */
    public static function compareCookiesForRetrieval(Cookie $a, Cookie $b): int
    {
        // sort by:
        // 1. path length, descending
        return match ($cmp = \strlen($b->path) <=> \strlen($a->path)) {
            // 2. creation date, ascending
            0 => $a->createdAt <=> $b->createdAt,
            default => $cmp,
        };
    }

    private static function serializeCookieListForRetrieval(array $cookies): string
    {
        $header = [];
        foreach ($cookies as $cookie) {
            if ('' !== $serialized = (string)$cookie) {
                $header[] = $serialized;
            };
        }
        return implode('; ', $header);
    }
}
