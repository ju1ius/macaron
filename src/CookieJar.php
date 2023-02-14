<?php declare(strict_types=1);

namespace ju1ius\Macaron;

use ju1ius\Macaron\Clock\UTCClock;
use ju1ius\Macaron\Cookie\SetCookieParser;
use ju1ius\Macaron\Internal\Rfc6265BisRetrievalModelTrait;
use ju1ius\Macaron\Internal\Rfc6265BisStorageModelTrait;
use ju1ius\Macaron\Policy\CookiePolicyInterface;
use ju1ius\Macaron\Policy\DefaultPolicy;
use ju1ius\Macaron\Storage\PersistentStorageInterface;
use ju1ius\Macaron\Uri\UriService;
use Psr\Clock\ClockInterface;
use Traversable;

/**
 * @link https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html
 */
final class CookieJar implements \IteratorAggregate
{
    use Rfc6265BisStorageModelTrait;
    use Rfc6265BisRetrievalModelTrait;

    /**
     * @var Cookie[][][]
     */
    private array $cookies = [];
    private readonly SetCookieParser $parser;
    private bool $hasLoadedCookiesFromStorage = false;

    public function __construct(
        private readonly UriService $uriService = new UriService(),
        private readonly CookiePolicyInterface $policy = new DefaultPolicy(),
        private readonly ClockInterface $clock = new UTCClock(),
        private readonly ?PersistentStorageInterface $persistentStorage = null,
    ) {
        $this->parser = new SetCookieParser();
    }

    /**
     * Sets whether the cookie jar should persist session (non-persistent) cookies.
     * This has no effect if there is no persistent storage.
     */
    public function setPersistSessionCookies(bool $persist): void
    {
        $this->persistentStorage?->setPersistSessionCookies($persist);
    }

    public function isEmpty(): bool
    {
        /** @noinspection PhpLoopNeverIteratesInspection */
        foreach ($this->getIterator() as $_) {
            return false;
        }
        return true;
    }

    /**
     * @return Cookie[]
     */
    public function all(): array
    {
        return iterator_to_array($this->getIterator());
    }

    /**
     * Removes all cookies from the cookie jar.
     */
    public function clear(): void
    {
        $this->cookies = [];
        $this->persistentStorage?->clear();
    }

    /**
     * Removes session (non-persistent) cookies from the cookie jar.
     *
     * @return int The number of evicted cookies.
     */
    public function clearSession(): int
    {
        return $this->removeMatching(static fn(Cookie $c) => !$c->persistent);
    }

    /**
     * Removes expired cookies from the cookie jar.
     *
     * @return int The number of evicted cookies.
     */
    public function clearExpired(): int
    {
        $now = $this->clock->now()->getTimestamp();
        return $this->removeMatching(static fn(Cookie $c) => $c->expiresAt <= $now);
    }

    /**
     * @todo Validate user-provided cookies
     */
    public function store(Cookie $cookie): void
    {
        if ($oldCookie = $this->has($cookie)) {
            $cookie->createdAt = $oldCookie->createdAt;
            $this->persistentStorage?->delete($oldCookie);
        }
        $this->set($cookie);
        $this->persistentStorage?->add($cookie);
    }

    /**
     * Unconditionally removes a cookie from the cookie jar.
     */
    public function remove(Cookie $cookie): void
    {
        $this->persistentStorage?->delete($cookie);
        unset($this->cookies[$cookie->domain][$cookie->path][$cookie->name]);
    }

    /**
     * Yields cookies for which `$predicate` returns a truthy value.
     *
     * @param callable(Cookie):bool $matchCookie
     * @param null|callable(string):bool $matchDomain
     * @param null|callable(string):bool $matchPath
     * @return Traversable<Cookie>
     */
    public function matching(
        callable $matchCookie,
        ?callable $matchDomain = null,
        ?callable $matchPath = null,
    ): Traversable {
        $this->loadAllFromPersistentStorage();
        foreach ($this->cookies as $domain => $inDomain) {
            if ($matchDomain && !$matchDomain($domain)) {
                continue;
            }
            foreach ($inDomain as $path => $inPath) {
                if ($matchPath && !$matchPath($path)) {
                    continue;
                }
                foreach ($inPath as $cookie) {
                    if ($matchCookie($cookie)) {
                        yield $cookie;
                    }
                }
            }
        }
    }

    /**
     * Returns true if all predicates match.
     *
     * @param callable(Cookie):bool $matchCookie
     * @param null|callable(string):bool $matchDomain
     * @param null|callable(string):bool $matchPath
     * @return bool
     */
    public function any(
        callable $matchCookie,
        ?callable $matchDomain = null,
        ?callable $matchPath = null,
    ): bool {
        $this->loadAllFromPersistentStorage();
        foreach ($this->cookies as $domain => $inDomain) {
            if ($matchDomain && !$matchDomain($domain)) {
                continue;
            }
            foreach ($inDomain as $path => $inPath) {
                if ($matchPath && !$matchPath($path)) {
                    continue;
                }
                foreach ($inPath as $cookie) {
                    if ($matchCookie($cookie)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Removes cookies for which `$predicate` returns a truthy value.
     *
     * @param callable(Cookie):bool $predicate
     * @return int The number of evicted cookies.
     */
    public function removeMatching(callable $predicate): int
    {
        $deleted = 0;
        foreach ($this->matching($predicate) as $cookie) {
            $deleted++;
            $this->remove($cookie);
        }

        return $deleted;
    }

    /**
     * @return Traversable<Cookie>
     */
    public function getIterator(): Traversable
    {
        $this->loadAllFromPersistentStorage();
        foreach ($this->cookies as $inDomain) {
            foreach ($inDomain as $inPath) {
                foreach ($inPath as $cookie) {
                    yield $cookie;
                }
            }
        }
    }

    private function set(Cookie $cookie): Cookie
    {
        return $this->cookies[$cookie->domain][$cookie->path][$cookie->name] = $cookie;
    }

    private function has(Cookie $cookie): ?Cookie
    {
        return $this->cookies[$cookie->domain][$cookie->path][$cookie->name] ?? null;
    }

    private function loadAllFromPersistentStorage(): void
    {
        if (!$this->persistentStorage || $this->hasLoadedCookiesFromStorage) {
            return;
        }
        foreach ($this->persistentStorage->load() as $cookie) {
            $this->set($cookie);
        }
        $this->hasLoadedCookiesFromStorage = true;
    }
}
