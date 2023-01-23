<?php declare(strict_types=1);

namespace ju1ius\Macaron\Internal;

use ju1ius\Macaron\Cookie;
use ju1ius\Macaron\Cookie\CookiePath;
use ju1ius\Macaron\Cookie\Domain;
use ju1ius\Macaron\Cookie\ParseError;
use ju1ius\Macaron\Cookie\ResponseCookie;
use ju1ius\Macaron\Cookie\SameSite;
use ju1ius\Macaron\Http\HttpMethod;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * @link https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html#name-storage-model
 */
trait Rfc6265BisStorageModelTrait
{
    public function updateFromResponse(
        RequestInterface $request,
        ResponseInterface $response,
        bool $isRequestSameSite = true,
    ): void {
        $this->updateFromGenericResponse(
            HttpMethod::of($request->getMethod()),
            $request->getUri(),
            $response->getStatusCode(),
            $response->getHeader('set-cookie'),
            $isRequestSameSite,
        );
    }

    public function updateFromGenericResponse(
        HttpMethod $requestMethod,
        UriInterface $requestUri,
        int $statusCode,
        array $setCookieHeaders,
        bool $isSameSite = true,
    ): void {
        if (!$this->policy->shouldAcceptResponse($requestMethod, $requestUri, $statusCode)) {
            return;
        }
        $now = $this->clock->now();
        $domain = Domain::of($requestUri);
        $isHttp = \in_array($requestUri->getScheme(), ['http', 'https']);
        $isSecure = $this->policy->isRequestSecure($requestUri);
        foreach ($setCookieHeaders as $header) {
            try {
                $parsed = $this->parser->parse($header);
            } catch (ParseError) {
                continue;
            }
            $this->storeResponseCookie(
                $parsed,
                $now,
                $statusCode,
                $requestMethod,
                $requestUri,
                $domain,
                $isHttp,
                $isSecure,
                $isSameSite,
            );
        }
        $this->persistentStorage?->flush();
    }

    private function storeResponseCookie(
        ResponseCookie $received,
        \DateTimeImmutable $now,
        int $statusCode,
        HttpMethod $requestMethod,
        UriInterface $requestUri,
        Domain $requestDomain,
        bool $isRequestHttp,
        bool $isRequestSecure,
        bool $isRequestSameSite,
    ): ?Cookie {
        $policy = $this->policy;
        // 1. TODO: may ignore
        // Steps 2, 3, 4 are covered by the parsing phase
        // 5.
        $cookie = new Cookie($received->name, $received->value, createdAt: $now);
        // 6.
        $maxExpiry = $policy->getMaxExpiry();
        if ($received->maxAge !== null) {
            $cookie->persistent = true;
            // see also sections 5.5.1 and 5.5.2
            if ($received->maxAge <= 0) {
                $cookie->expiresAt = \PHP_INT_MIN;
            } else {
                $maxAge = min($received->maxAge, $maxExpiry);
                $cookie->expiresAt = $now->getTimestamp() + $maxAge;
            }
        } else if ($received->expires) {
            $cookie->persistent = true;
            $expires = $received->expires->getTimestamp();
            $cookie->expiresAt = max(\PHP_INT_MIN, min($expires, $now->getTimestamp() + $maxExpiry));
        } else {
            $cookie->persistent = false;
            $cookie->expiresAt = \PHP_INT_MAX;
        }
        // 7. (already handled by the parsing step)
        // 8. TODO: this should have been checked by the set-cookie parser already...
        if (!Str::isAscii($received->domain)) {
            return null;
        }
        // 9.
        if (!$policy->isAllowingPublicSuffixes() && $this->uriService->isPublicSuffix($received->domain)) {
            if ($requestDomain->equals($received->domain)) {
                $received->domain = '';
            } else {
                return null;
            }
        }
        // 10.
        if ($received->domain !== '') {
            if (!$requestDomain->matches($received->domain)) {
                return null;
            } else {
                $cookie->hostOnly = false;
                $cookie->domain = $received->domain;
            }
        } else {
            $cookie->hostOnly = true;
            $cookie->domain = (string)$requestDomain;
        }
        // 11.
        if ($received->path) {
            $cookie->path = $received->path;
        } else {
            $cookie->path = CookiePath::default($requestUri);
        }
        // 12.
        if ($received->secure) {
            $cookie->secureOnly = true;
        }
        // 13.
        if ($cookie->secureOnly && !$isRequestSecure) {
            return null;
        }
        // 14.
        if ($received->httpOnly) {
            $cookie->httpOnly = true;
        }
        // 15.
        if ($cookie->httpOnly && !$isRequestHttp) {
            return null;
        }
        // 16.
        if (!$cookie->secureOnly && !$isRequestSecure) {
            if ($this->containsMatchingSecureOnlyCookies($cookie)) {
                return null;
            }
        }
        // 17.
        $cookie->sameSite = $received->sameSite;
        // 18. (we only care about step 2 since we have no nested browsing contexts)
        if ($cookie->sameSite !== SameSite::None && !$isRequestSameSite) {
            return null;
        }
        // 19.
        if ($cookie->sameSite === SameSite::None && !$cookie->secureOnly) {
            return null;
        }
        // 20.
        $hasSecurePrefix = Str::iStartsWith($cookie->name, '__Secure-');
        if ($hasSecurePrefix && !$cookie->secureOnly) {
            return null;
        }
        $hasHostPrefix = Str::iStartsWith($cookie->name, '__Host-');
        // 21.
        if ($hasHostPrefix && !($cookie->secureOnly && $cookie->hostOnly && $cookie->path === '/')) {
            return null;
        }
        // 22.
        if ($cookie->name === '' && (
            Str::iStartsWith($cookie->value, '__Secure-')
            || Str::iStartsWith($cookie->value, '__Host-')
        )) {
            return null;
        }

        if (!$policy->shouldAcceptCookie($cookie, $requestMethod, $requestUri, $statusCode)) {
            return null;
        }

        // 23.
        if ($oldCookie = $this->has($cookie)) {
            if (!$isRequestHttp && $oldCookie->httpOnly) {
                return null;
            }
            $cookie->createdAt = $oldCookie->createdAt;
            $this->persistentStorage?->delete($oldCookie);
        }
        // 24.
        $this->persistentStorage?->add($cookie);
        return $this->set($cookie);
    }

    private function containsMatchingSecureOnlyCookies(Cookie $cookie): bool
    {
        $cookieDomain = Domain::of($cookie->domain);
        return $this->any(
            fn(Cookie $existing) => $existing->secureOnly && $existing->name === $cookie->name,
            fn($domain) => $cookieDomain->matches($domain),
            fn($path) => CookiePath::matches($cookie, $path),
        );
    }
}
