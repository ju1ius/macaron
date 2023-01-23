<?php declare(strict_types=1);

namespace ju1ius\Macaron\Http;

enum HttpMethod: string
{
    case Get = 'GET';
    case Head = 'HEAD';
    case Post = 'POST';
    case Put = 'PUT';
    case Delete = 'DELETE';
    case Connect = 'CONNECT';
    case Options = 'OPTIONS';
    case Trace = 'TRACE';
    case Patch = 'PATCH';

    /**
     * Request methods are considered "safe" if their defined semantics are essentially read-only;
     * i.e., the client does not request, and does not expect,
     * any state change on the origin server as a result of applying a safe method to a target resource.
     *
     * @link https://www.rfc-editor.org/rfc/rfc9110#name-safe-methods
     */
    public function isSafe(): bool
    {
        return match ($this) {
            self::Get,
            self::Head,
            self::Options,
            self::Trace => true,
            default => false,
        };
    }

    /**
     * A request method is considered "idempotent" if the intended effect on the server
     * of multiple identical requests with that method is the same as the effect for a single such request.
     *
     * @link https://www.rfc-editor.org/rfc/rfc9110#name-idempotent-methods
     */
    public function isIdemPotent(): bool
    {
        return match ($this) {
            self::Get,
            self::Head,
            self::Put,
            self::Delete,
            self::Options,
            self::Trace => true,
            default => false,
        };
    }

    public static function of(self|string $method): self
    {
        if ($method instanceof self) {
            return $method;
        }
        if ($self = self::tryFrom($method)) {
            return $self;
        }

        return self::from(strtoupper($method));
    }
}
