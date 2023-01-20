<?php declare(strict_types=1);

namespace ju1ius\Macaron;

/**
 * @internal
 */
final class HostOptions
{
    private const PRESERVED_HEADERS = [
        'authorization' => true,
    ];

    private array $byHost = [];

    public function storeForUri(string $uri, array $options): void
    {
        $host = parse_url($uri, \PHP_URL_HOST);
        $this->byHost[$host] = [
            'auth_basic' => $options['auth_basic'] ?? null,
            'auth_bearer' => $options['auth_bearer'] ?? null,
            'headers' => self::filterPreservedHeaders($options),
        ];
    }

    public function updateForRedirect(string $location, array $options): array
    {
        $host = parse_url($location, \PHP_URL_HOST);
        return array_merge($options, $this->byHost[$host] ?? [
            'auth_basic' => null,
            'auth_bearer' => null,
            'headers' => null,
        ]);
    }

    private static function filterPreservedHeaders(array $options): array
    {
        return array_filter(
            $options['headers'] ?? [],
            fn($v, $k) => self::PRESERVED_HEADERS[strtolower($k)] ?? false,
            \ARRAY_FILTER_USE_BOTH,
        );
    }
}
