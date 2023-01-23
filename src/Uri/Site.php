<?php declare(strict_types=1);

namespace ju1ius\Macaron\Uri;

use ju1ius\Macaron\Exception\InvalidUriException;

/**
 * @link https://html.spec.whatwg.org/#sites
 */
final class Site implements \Stringable
{
    public function __construct(
        private readonly string $scheme,
        private readonly string $host,
    ) {
        if (!$this->scheme || !$this->host) {
            throw InvalidUriException::invalidSite($this);
        }
    }

    public function isSameSite(self $other): bool
    {
        if ($this === $other) {
            return true;
        }
        return (
            $this->scheme === $other->scheme
            && $this->host === $other->host
        );
    }

    public function __toString(): string
    {
        return "{$this->scheme}://{$this->host}";
    }
}
