<?php declare(strict_types=1);

namespace ju1ius\Macaron\Http;

use ju1ius\Macaron\Uri\Origin;
use ju1ius\Macaron\Uri\Site;
use ju1ius\Macaron\Uri\UriService;
use Psr\Http\Message\UriInterface;

/**
 * Helper class to ease computing same-origin and same-site status of a request chain.
 */
final class RequestChain
{
    private Origin $origin;
    /**
     * @var Site[]
     */
    private array $sites = [];

    private bool $sameOrigin = true;
    private bool $sameSite = true;

    public function __construct(
        private readonly UriService $uriService = new UriService(),
        /**
         * @TODO: this flag should be handled by the cookie policy
         * @link https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html#name-lax-allowing-unsafe-enforce
         */
        private readonly bool $allowCrossSiteSameSite = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return empty($this->sites);
    }

    public function start(UriInterface $requestUri): void
    {
        $this->origin = $this->uriService->getOrigin($requestUri);
        $this->sites = [
            $this->uriService->getSite($requestUri),
        ];
        $this->sameSite = $this->sameOrigin = true;
    }

    public function next(UriInterface $location): void
    {
        if ($this->isEmpty()) {
            throw new \LogicException('The request chain is empty.');
        }
        $origin = $this->uriService->getOrigin($location);
        $this->sites[] = $this->uriService->getSite($location);
        $this->sameOrigin  = $this->origin->isSameOrigin($origin);
        $this->sameSite = $this->computeSameSite();
    }

    public function finish(): void
    {
        $this->sites = [];
        $this->sameSite = $this->sameOrigin = true;
    }

    public function isSameOrigin(): bool
    {
        return $this->sameOrigin;
    }

    public function isSameSite(): bool
    {
        return $this->sameSite;
    }

    private function computeSameSite(): bool
    {
        $last = \count($this->sites) - 1;
        $current = $this->sites[$last];

        if ($this->allowCrossSiteSameSite) {
            return $this->sites[0]->isSameSite($current);
        }

        for ($i = 0; $i < $last; $i++) {
            if (!$current->isSameSite($this->sites[$i])) {
                return false;
            }
        }

        return true;
    }
}
