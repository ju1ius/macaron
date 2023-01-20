<?php declare(strict_types=1);

namespace ju1ius\Macaron;

use Symfony\Component\BrowserKit\Cookie as BKCookie;
use Symfony\Component\BrowserKit\CookieJar as BaseCookieJar;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

final class CookieJar extends BaseCookieJar
{
    public static function of(array|self $cookies): self
    {
        if ($cookies instanceof self) {
            return $cookies;
        }

        $jar = new self();
        foreach ($cookies as $key => $value) {
            if (\is_scalar($value)) {
                $jar->set(new Cookie($key, (string)$value));
            } else {
                try {
                    $jar->set($value);
                } catch (\TypeError) {
                    continue;
                }
            }
        }

        return $jar;
    }

    public function set(Cookie|BKCookie|HttpCookie $cookie)
    {
        parent::set(Cookie::of($cookie));
    }

    public function asCookieHeader(string $uri): string
    {
        $cookies = [];
        foreach ($this->allRawValues($uri) as $name => $value) {
            $cookies[] = "{$name}={$value}";
        }

        return implode('; ', $cookies);
    }
}