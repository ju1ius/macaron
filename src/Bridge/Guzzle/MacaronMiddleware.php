<?php declare(strict_types=1);

namespace Souplette\Macaron\Bridge\Guzzle;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Http\RequestChain;
use Souplette\Macaron\Uri\UriService;

final class MacaronMiddleware
{
    private ?RequestChain $chain;

    private function __construct(
        private readonly CookieJar $jar,
        private readonly UriService $uriService,
        /**
         * @var callable(RequestInterface, array): PromiseInterface
         */
        private readonly \Closure $nextHandler,
    ) {
        $this->chain = new RequestChain($this->uriService);
    }

    public static function create(CookieJar $jar, UriService $uriService): callable
    {
        return function($handler) use($jar, $uriService) {
            return new self($jar, $uriService, $handler(...));
        };
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        $next = $this->nextHandler;
        if ($this->chain->isEmpty()) {
            $this->chain->start($request->getUri());
        } else {
            $this->chain->next($request->getUri());
        }
        $cookie = $this->jar->retrieveForRequest($request, $this->chain->isSameSite());
        $request = $request->withHeader('cookie', $cookie);

        return $next($request, $options)->then(function (ResponseInterface $response) use ($request) {
            $this->jar->updateFromResponse($request, $response, $this->chain->isSameSite());
            if (!self::isRedirect($response)) {
                $this->chain->finish();
            }
            return $response;
        });
    }

    private static function isRedirect(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();
        return $status >= 300 && $status < 400 && $response->getHeaderLine('location');
    }
}
