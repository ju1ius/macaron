<?php declare(strict_types=1);

namespace ju1ius\Macaron\Cookie;

use Psr\Http\Message\UriInterface;
use Souplette\FusBup\Exception\IdnException;
use Souplette\FusBup\Utils\Idn;

/**
 * @todo remove this class?
 */
final class Domain implements \Stringable
{
    private function __construct(
        private readonly string $domain,
        private readonly string $canonical,
        private readonly bool $isIpAddress,
    ) {
    }

    public static function of(string|UriInterface|self $domain): self
    {
        if ($domain instanceof self) {
            return $domain;
        }

        if ($domain instanceof UriInterface) {
            $domain = $domain->getHost();
        }
        if ($ip = self::parseIpAddress($domain)) {
            $canonical = $ip;
        } else {
            try {
                $canonical = Idn::toAscii($domain);
            } catch (IdnException) {
                $canonical = strtolower($domain);
            }
        }

        return new self($domain, $canonical, $ip !== null);
    }

    public function equals(string|self $other): bool
    {
        return $this->canonical === self::of($other)->canonical;
    }

    /**
     * @link https://httpwg.org/specs/rfc6265.html#cookie-domain
     */
    public function matches(string|self $domain): bool
    {
        $domain = self::of($domain);
        // A string domain-matches a given domain string if at least one of the following conditions hold:
        // 1. The domain string and the string are identical.
        //    Note that both the domain string and the string will have been canonicalized to lower case at this point.
        if ($this->equals($domain)) {
            return true;
        }
        // 2. All the following conditions hold:
        return (
            // The domain string is a suffix of the string
            str_ends_with($this->canonical, $domain->canonical)
            // The last character of the string that is not included in the domain string is "."
            && $this->canonical[-\strlen($domain->canonical) - 1] === '.'
            // The string is a host name (i.e., not an IP address).
            && !$this->isIpAddress
        );
    }

    public function isIpAddress(): bool
    {
        return $this->isIpAddress;
    }

    public function __toString(): string
    {
        return $this->domain;
    }

    private static function parseIpAddress(string $host): ?string
    {
        if ($host === '') {
            return null;
        }
        if ($host[0] === '[' && $host[-1] === ']') {
            $host = substr($host, 1, -1);
        }

        return filter_var($host, \FILTER_VALIDATE_IP, \FILTER_NULL_ON_FAILURE);
    }
}
