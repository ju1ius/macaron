<?php declare(strict_types=1);

namespace Souplette\Macaron\Cookie;

/**
 * Value object representing the result of parsing a Set-Cookie header.
 *
 * @link https://httpwg.org/specs/rfc6265.html#rfc.section.5.2
 * @internal
 */
final class ResponseCookie implements \Stringable
{
    /**
     * @link https://datatracker.ietf.org/doc/html/draft-ietf-httpbis-semantics-19#section-5.6.7
     */
    private const DATE_FORMAT = \DateTimeInterface::RFC7231;

    public function __construct(
        public string $name,
        public string $value,
        public string $domain = '',
        public ?string $path = null,
        public ?\DateTimeImmutable $expires = null,
        public ?int $maxAge = null,
        public bool $secure = false,
        public bool $httpOnly = false,
        public SameSite $sameSite = SameSite::Default,
    ) {
    }

    public function __toString(): string
    {
        $pairs = [];

        if ($this->name === '') {
            $pairs[] = $this->value;
        } else {
            $pairs[] = "{$this->name}={$this->value}";
        }

        if ($domain = $this->domain) {
            $pairs[] = "domain={$domain}";
        }
        if ($path = $this->path) {
            $pairs[] = "path={$path}";
        }
        if ($expires = $this->expires) {
            $pairs[] = 'expires=' . $expires->format(self::DATE_FORMAT);
        }
        if ($this->maxAge !== null) {
            $pairs[] = "max-age={$this->maxAge}";
        }
        if ($this->secure) {
            $pairs[] = 'secure';
        }
        if ($this->httpOnly) {
            $pairs[] = 'httponly';
        }
        if ($this->sameSite !== SameSite::Default) {
            $pairs[] = "samesite={$this->sameSite->value}";
        }

        return implode('; ', $pairs);
    }
}
