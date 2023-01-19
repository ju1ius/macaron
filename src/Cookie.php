<?php declare(strict_types=1);

namespace ju1ius\Macaron;

use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

final class Cookie extends BrowserKitCookie
{
    public static function of(HttpCookie|BrowserKitCookie|self $cookie): self
    {
        if ($cookie instanceof self) {
            return $cookie;
        }

        if ($cookie instanceof HttpCookie) {
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
}
