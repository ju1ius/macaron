<?php declare(strict_types=1);

namespace Souplette\Macaron\Uri;

use Psr\Http\Message\UriInterface;
use Souplette\Macaron\Exception\InvalidUriException;

/**
 * Naive implementation of the PSR UriInterface.
 * @internal
 */
final class Uri implements UriInterface, \Stringable
{
    private const DEFAULTS = [
        'scheme' => '',
        'host' => '',
        'port' => null,
        'user' => '',
        'pass' => '',
        'path' => '',
        'query' => '',
        'fragment' => '',
    ];

    private function __construct(
        private string $scheme = '',
        private string $host = '',
        private ?int $port = null,
        private string $user = '',
        private string $pass = '',
        private string $path = '',
        private string $query = '',
        private string $fragment = '',
    ) {
    }

    public static function of(UriInterface|string $uri): self
    {
        if (\is_string($uri)) {
            return self::parse($uri);
        }
        if ($uri instanceof self) {
            return $uri;
        }

        [$user, $pass] = explode(':', $uri->getUserInfo(), 2) + ['', ''];
        return new self(
            $uri->getScheme(),
            $uri->getHost(),
            $uri->getPort(),
            $user,
            $pass,
            $uri->getPath(),
            $uri->getQuery(),
            $uri->getFragment(),
        );
    }

    /**
     * @link https://url.spec.whatwg.org/#default-port
     */
    public static function defaultPort(UriInterface|string $scheme): ?int
    {
        if ($scheme instanceof UriInterface) {
            $scheme = $scheme->getScheme() ?: 'http';
        } else {
            $scheme = strtolower($scheme);
        }
        return match ($scheme) {
            'http', 'ws' => 80,
            'https', 'wss' => 443,
            'ftp' => 21,
            default => null,
        };
    }

    // PSR-7 boilerplate

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        $authority = '';
        if ($host = $this->host) {
            if ($userInfo = $this->getUserInfo()) {
                $authority = $userInfo . '@';
            }
            $authority .= $host;
            if ($this->port && $this->port !== self::defaultPort($this->scheme)) {
                $authority .= ':' . $this->port;
            }
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        if ($this->user) {
            return $this->pass ? "{$this->user}:{$this->pass}" : $this->user;
        }
        return '';
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme($scheme): self
    {
        if (!\is_string($scheme)) {
            throw self::invalidArgumentType('$scheme', __METHOD__, 'string', $scheme);
        }
        $self = clone $this;
        $self->scheme = \strtolower($scheme);
        return $self;
    }

    public function withUserInfo($user, $password = null): self
    {
        if (!\is_string($user)) {
            throw self::invalidArgumentType('$user', __METHOD__, 'string', $user);
        }
        if (!\is_string($password) && !\is_null($password)) {
            throw self::invalidArgumentType('$password', __METHOD__, 'string|null', $password);
        }
        $self = clone $this;
        $self->user = $user ?? '';
        $self->pass = $password ?? '';
        return $self;

    }

    public function withHost($host): self
    {
        if (!\is_string($host)) {
            throw self::invalidArgumentType('$host', __METHOD__, 'string', $host);
        }
        $self = clone $this;
        $self->host = $host;
        return $self;
    }

    public function withPort($port): self
    {
        if (!\is_int($port) && !\is_null($port)) {
            throw self::invalidArgumentType('$port', __METHOD__, 'int|null', $port);
        }
        $self = clone $this;
        $self->port = match ($port) {
            null => null,
            default => min(0xFFFF, max(0, $port))
        };
        return $self;
    }

    public function withPath($path): self
    {
        if (!\is_string($path)) {
            throw self::invalidArgumentType('$path', __METHOD__, 'string', $path);
        }
        $self = clone $this;
        $self->path = $path;
        return $self;
    }

    public function withQuery($query): self
    {
        if (!\is_string($query)) {
            throw self::invalidArgumentType('$query', __METHOD__, 'string', $query);
        }
        $self = clone $this;
        $self->query = $query;
        return $self;
    }

    public function withFragment($fragment): self
    {
        if (!\is_string($fragment)) {
            throw self::invalidArgumentType('$fragment', __METHOD__, 'string', $fragment);
        }
        $self = clone $this;
        $self->fragment = $fragment ?? '';
        return $self;
    }

    public function __toString(): string
    {
        $href = '';
        if ($scheme = $this->scheme) {
            $href .= $scheme . ':';
        }
        if ($authority = $this->getAuthority()) {
            $href .= '//' . $authority;
        }
        if ($path = $this->path) {
            if ($authority && !str_starts_with($path, '/')) {
                $path = '/' . $path;
            }
            if (!$authority && str_starts_with($path, '//')) {
                $path = '/' . ltrim($this->path, '/');
            }
            $href .= $path;
        }
        if ($query = $this->query) {
            $href .= '?' . $query;
        }
        if ($fragment = $this->fragment) {
            $href .= '#' . $fragment;
        }

        return $href;
    }

    private static function parse(string $uri): self
    {
        $parsed = \parse_url($uri);
        if (!$parsed) {
            throw InvalidUriException::forUri($uri);
        }
        $parsed += self::DEFAULTS;
        $scheme = \strtolower($parsed['scheme']);

        return new self(
            $scheme,
            \strtolower($parsed['host']),
            $parsed['port'] ?? self::defaultPort($scheme),
            $parsed['user'],
            $parsed['pass'],
            $parsed['path'],
            $parsed['query'],
            $parsed['fragment'],
        );
    }

    private static function invalidArgumentType(
        string $name,
        string $method,
        string $type,
        mixed $value
    ): \InvalidArgumentException {
        return new \InvalidArgumentException(sprintf(
            'Argument "%s" of %s must be of type "%s", "%s" given.',
            $name,
            $method,
            $type,
            get_debug_type($value),
        ));
    }
}
