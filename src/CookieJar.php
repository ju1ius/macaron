<?php declare(strict_types=1);

namespace ju1ius\Macaron;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar as BaseCookieJar;

final class CookieJar extends BaseCookieJar
{
    public static function of(array|self $cookies): self
    {
        if ($cookies instanceof self) {
            return $cookies;
        }

        $jar = new self();
        foreach ($cookies as $key => $value) {
            if ($cookie = match (true) {
                $value instanceof Cookie => $value,
                \is_scalar($value) => new Cookie($key, (string)$value),
                default => null,
            }) {
                $jar->set($cookie);
            }
        }

        return $jar;
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
