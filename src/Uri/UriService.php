<?php declare(strict_types=1);

namespace ju1ius\Macaron\Uri;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Souplette\FusBup\Exception\IdnException;
use Souplette\FusBup\PublicSuffixList;
use Souplette\FusBup\PublicSuffixListInterface;

final class UriService implements UriFactoryInterface
{
    public function __construct(
        private readonly UriFactoryInterface $uriFactory = new UriFactory(),
        private readonly PublicSuffixListInterface $publicSuffixList = new PublicSuffixList(),
    ) {
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return $this->uriFactory->createUri($uri);
    }

    public function getOrigin(UriInterface $uri): Origin
    {
        return new Origin(
            $scheme = $uri->getScheme() ?: 'http',
            $uri->getHost(),
            $uri->getPort() ?? Uri::defaultPort($scheme),
        );
    }

    /**
     * @link https://html.spec.whatwg.org/multipage/browsers.html#obtain-a-site
     */
    public function getSite(UriInterface $uri): Site
    {
        // If origin is an opaque origin, then return origin.
        // If origin's host's registrable domain is null, then return (origin's scheme, origin's host).
        // Return (origin's scheme, origin's host's registrable domain).
        $host = $uri->getHost();
        $scheme = $uri->getScheme() ?: 'http';
        if ($domain = $this->publicSuffixList->getRegistrableDomain($host)) {
            return new Site($scheme, $domain);
        }
        return new Site($scheme, $host);
    }

    public function isPublicSuffix(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }
        try {
            return $this->publicSuffixList->isEffectiveTLD($domain);
        } catch (IdnException) {
            return false;
        }
    }
}
