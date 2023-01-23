<?php declare(strict_types=1);

namespace ju1ius\Macaron\Storage;

use ju1ius\Macaron\Cookie;

interface PersistentStorageInterface
{
    /**
     * Sets whether the storage should persist session (non-persistent) cookies.
     */
    public function setPersistSessionCookies(bool $persist): void;

    /**
     * Deletes all cookies in the store.
     */
    public function clear(): void;

    /**
     * Loads all cookies in store.
     *
     * @return Cookie[]
     */
    public function load(): array;

    /**
     * Loads all cookies for the given domains.
     *
     * @return Cookie[]
     */
    public function loadDomains(string ...$keys): array;

    /**
     * Queues a task to add a cookie to the store.
     */
    public function add(Cookie $cookie): void;

    /**
     * Queues a task to remove a cookie from the store.
     */
    public function delete(Cookie $cookie): void;

    /**
     * Queues a task to update a cookie last access time.
     */
    public function touch(Cookie $cookie): void;

    /**
     * Executes all pending storage tasks.
     */
    public function flush(): void;
}
