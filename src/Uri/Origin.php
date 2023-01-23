<?php declare(strict_types=1);

namespace ju1ius\Macaron\Uri;

use ju1ius\Macaron\Exception\InvalidUriException;
use Psr\Http\Message\UriInterface;

final class Origin implements \Stringable
{
    public function __construct(
        private readonly string $scheme,
        private readonly string $host,
        private readonly ?int $port,
        private ?string $domain = null,
    ) {
        if (!$this->scheme || !$this->host) {
            throw InvalidUriException::invalidOrigin($this);
        }
    }

    public static function of(UriInterface|self $origin): self
    {
        if ($origin instanceof self) {
            return $origin;
        }

        return new self(
            $origin->getScheme() ?: 'http',
            $origin->getHost(),
            $origin->getPort() ?? Uri::defaultPort($origin),
        );
    }

    /**
     * @link https://html.spec.whatwg.org/multipage/browsers.html#concept-origin-effective-domain
     */
    public function getEffectiveDomain(): string
    {
        return $this->domain ?? $this->host;
    }

    /**
     * @link https://html.spec.whatwg.org/multipage/browsers.html#same-origin
     */
    public function isSameOrigin(self $other): bool
    {
        if ($this === $other) {
            return true;
        }
        return (
            $this->scheme === $other->scheme
            && $this->host === $other->host
            && $this->port === $other->port
        );
    }

    /**
     * @link https://html.spec.whatwg.org/multipage/browsers.html#same-origin-domain
     */
    public function isSameOriginDomain(self $other): bool
    {
        if ($this->domain !== $other->domain) {
            return false;
        }
        return match ($this->domain) {
            null => $this->isSameOrigin($other),
            default => $this->scheme === $other->scheme,
        };
    }

    public function __toString(): string
    {
        if ($this->port) {
            return "{$this->scheme}://{$this->host}:{$this->port}";
        }
        return "{$this->scheme}://{$this->host}";
    }
}
