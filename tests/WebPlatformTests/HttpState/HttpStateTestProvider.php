<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\WebPlatformTests\HttpState;

use ju1ius\Macaron\Cookie;
use ju1ius\Macaron\Tests\ResourceHelper;

final class HttpStateTestProvider
{
    public static function provideTestCases(): iterable
    {
        $baseUri = 'http://home.example.org:8888';
        foreach (ResourceHelper::json('http-state/parser.json') as $fixture) {
            $query = strtr(strtolower($fixture['test']), ['_' => '-']);
            $uri = "{$baseUri}/cookie-parser?{$query}";
            $location = $fixture['sent-to'] ?? "/cookie-parser-result?{$query}";
            if (!parse_url($location, \PHP_URL_HOST)) {
                $location = $baseUri . '/' . ltrim($location, '/');
            }
            $cookies = $fixture['sent'] ?? [];
            $cookieHeader = [];
            foreach ($cookies as $cookie) {
                $cookieHeader[] = new Cookie($cookie['name'], $cookie['value']);
            }

            $dto = new HttpStateTestDto(
                $query,
                $uri,
                array_map(self::fixSetCookieHeader(...), $fixture['received'] ?? []),
                $location,
                implode('; ', $cookieHeader),
            );
            yield (string)$dto => $dto;
        }
    }

    private static function fixSetCookieHeader(string $header): string
    {
        return preg_replace_callback(
            '/\bExpires=(?<date>PAST|FUTURE)\b/i',
            self::replaceSetCookieDate(...),
            $header,
        );
    }

    /**
     * The `http-state` repository stopped receiving commit around August 2017,
     * so some then-far-future dates are now in the past.
     */
    private static function replaceSetCookieDate(array $matches): string
    {
        $date = match (strtolower($matches['date'])) {
            'past' => new \DateTimeImmutable('now -1 year'),
            'future' => new \DateTimeImmutable('now +1 year'),
        };
        return 'expires=' . $date->format(\DATE_RFC7231);
    }
}
