<?php declare(strict_types=1);

namespace Souplette\Macaron\Cookie;

/**
 * @link https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html#name-the-set-cookie-header-field
 * @internal
 */
final class SetCookieParser
{
    public function parse(string $header): ResponseCookie
    {
        if ($header === '') {
            throw ParseError::emptyHeader();
        }
        if (!preg_match('/^[^\x00-\x08\x0A-\x1F\x7F]+$/', $header)) {
            throw ParseError::invalidCharacters();
        }

        $pairs = explode(';', $header);
        $cookie = null;
        foreach ($pairs as $i => $pair) {
            if ($i === 0) {
                $cookie = $this->parseNameValuePair($pair);
                continue;
            }
            $this->parseAttribute($pair, $cookie);
        }

        return $cookie;
    }

    private function parseNameValuePair(string $input): ResponseCookie
    {
        $pair = self::splitPair($input);
        [$name, $value] = match (\count($pair)) {
            1 => ['', $pair[0]],
            2 => [$pair[0], $pair[1]],
        };
        if (\strlen($name) + \strlen($value) > 4096) {
            throw ParseError::nameValueLimit();
        }
        return new ResponseCookie($name, $value);
    }

    private function parseAttribute(string $input, ResponseCookie $cookie): void
    {
        $pair = self::splitPair($input);

        if (\count($pair) === 2) {
            [$name, $value] = $pair;
            if (\strlen($value) > 1024) {
                return;
            }
            switch (\strtolower($name)) {
                case 'expires':
                    $this->parseExpires($value, $cookie);
                    return;
                case 'max-age':
                    $this->parseMaxAge($value, $cookie);
                    return;
                case 'domain':
                    $this->parseDomain($value, $cookie);
                    return;
                case 'path':
                    $this->parsePath($value, $cookie);
                    return;
                case 'samesite':
                    $this->parseSameSite($value, $cookie);
                    return;
                default:
                    break;
            }
        }

        $key = $pair[0];
        match (\strtolower($key)) {
            'secure' => $cookie->secure = true,
            'httponly' => $cookie->httpOnly = true,
            default => null,
        };
    }

    /**
     * @link https://httpwg.org/specs/rfc6265.html#expires-attribute
     */
    private function parseExpires(string $input, ResponseCookie $cookie): void
    {
        if ($date = CookieDateParser::parse($input)) {
            $cookie->expires = $date;
        }
    }

    /**
     * @link https://httpwg.org/specs/rfc6265.html#max-age-attribute
     */
    private function parseMaxAge(string $input, ResponseCookie $cookie): void
    {
        if ($input === '') return;
        // expiry date computation is preformed when storing the cookie
        if (preg_match('/^-?\d+$/', $input)) {
            $cookie->maxAge = self::saturatingIntegerCast($input);
        }
    }

    /**
     * @link https://httpwg.org/specs/rfc6265.html#domain-attribute
     */
    private function parseDomain(string $input, ResponseCookie $cookie): void
    {
        if ($input === '') {
            $cookie->domain = '';
            return;
        }
        if ($input[0] === '.') {
            $input = substr($input, 1);
        }
        $input = strtolower($input);
        $cookie->domain = $input;
    }

    /**
     * @link https://httpwg.org/specs/rfc6265.html#path-attribute
     */
    private function parsePath(string $input, ResponseCookie $cookie): void
    {
        if ($input === '' || $input[0] !== '/') {
            $cookie->path = null;
        } else {
            $cookie->path = $input;
        }
    }

    private static function splitPair(string $input): array
    {
        return array_map(
            static fn(string $p) => trim($p, " \t"),
            explode('=', $input, 2),
        );
    }

    private function parseSameSite(mixed $value, ResponseCookie $cookie): void
    {
        $cookie->sameSite = match (strtolower($value)) {
            'lax' => SameSite::Lax,
            'strict' => SameSite::Strict,
            'none' => SameSite::None,
            default => SameSite::Default,
        };
    }

    private static function saturatingIntegerCast(string $input): int
    {
        // if $input is too large, casting via (int) or intval() produces 0,
        // whereas $input + 0 produces INF or -INF.
        // This allows returning a more sensible value.
        return min(\PHP_INT_MAX, max(\PHP_INT_MIN, $input + 0));
    }
}
