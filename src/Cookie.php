<?php declare(strict_types=1);

namespace ju1ius\Macaron;

use ju1ius\Macaron\Clock\UTCClock;
use ju1ius\Macaron\Cookie\SameSite;
use ju1ius\Macaron\Policy\CookiePolicyInterface;

final class Cookie implements \Stringable
{
    public \DateTimeImmutable $createdAt;
    public \DateTimeImmutable $accessedAt;
    public int $expiresAt;

    public function __construct(
        public string $name,
        public string $value,
        public string $domain = '',
        public string $path = '/',
        public bool $persistent = false,
        ?int $expiresAt = null,
        public bool $hostOnly = false,
        public bool $secureOnly = false,
        public bool $httpOnly = false,
        public SameSite $sameSite = SameSite::Default,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $accessedAt = null,
    ) {
        $this->createdAt = $createdAt ?? UTCClock::utcNow();
        $this->accessedAt = $accessedAt ?? $this->createdAt;
        $this->expiresAt = $expiresAt ?? match ($this->persistent) {
            false => \PHP_INT_MAX,
            true => UTCClock::utcNow()->getTimestamp() + CookiePolicyInterface::RECOMMENDED_MAX_EXPIRY,
        };
    }

    public function touch(\DateTimeImmutable $timestamp): void
    {
        $this->accessedAt = $timestamp;
    }

    public function __toString(): string
    {
        if ($this->name !== '') {
            return "{$this->name}={$this->value}";
        }
        return $this->value;
    }
}
