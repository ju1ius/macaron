<?php declare(strict_types=1);

namespace ju1ius\Macaron;

use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Component\HttpFoundation\Cookie as HttpFoundationCookie;

final class Cookie extends BrowserKitCookie
{
    /**
     * @var array<string, callable>
     */
    private static array $casters = [];

    /**
     * @template T
     * @param class-string $class
     * @param callable(T): Cookie $caster
     */
    public static function addCaster(string $class, callable $caster): void
    {
        self::$casters[$class] = $caster;
    }

    public static function of(object $cookie): self
    {
        if ($cookie instanceof self) {
            return $cookie;
        }
        if ($cookie instanceof BrowserKitCookie) {
            $self = new self(
                $cookie->name,
                $cookie->value,
                $cookie->expires,
                $cookie->path,
                $cookie->domain,
                $cookie->secure,
                $cookie->httponly,
                false,
                $cookie->getSameSite(),
            );
            $self->rawValue = $cookie->rawValue;
            return $self;
        }
        if ($cookie instanceof HttpFoundationCookie) {
            $expires = $cookie->getExpiresTime();
            $self = new self(
                $cookie->getName(),
                $cookie->getValue(),
                $expires ? (string)$expires : null,
                $cookie->getPath(),
                $cookie->getDomain() ?? '',
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
                false,
                $cookie->getSameSite(),
            );
            if ($cookie->isRaw()) {
                $self->rawValue = $cookie->getValue();
            }
            return $self;
        }

        if (
            ($caster = self::$casters[$cookie::class] ?? null)
            && ($self = $caster($cookie))
            && $self instanceof self
        ) {
            return $self;
        }

        throw new \TypeError(sprintf(
            'Could not cast object of type "%s" to "%s"',
            get_debug_type($cookie),
            self::class,
        ));
    }
}
