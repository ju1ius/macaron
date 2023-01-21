<?php declare(strict_types=1);

namespace ju1ius\Macaron\Internal;

use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * @internal
 */
final class ClientState
{
    private readonly string $authority;
    private array $filteredOptions;

    public function __construct(
        string $uri,
        private array $options,
    ) {
        $this->authority = self::parseAuthority($uri);
    }

    public function redirect(string $method, int $code, string $location): array
    {
        if (\in_array($code, [301, 302, 303], true)) {
            switch ($method) {
                case 'HEAD':
                case 'GET':
                    break;
                default:
                    $method = 'GET';
                    unset($this->options['query'], $this->options['body']);
                    unset($this->filteredOptions['query'], $this->filteredOptions['body']);
                    break;
            }
        }

        $authority = self::parseAuthority($location);
        $options = match ($authority === $this->authority) {
            true => $this->options,
            false => $this->filteredOptions ??= self::filterOptions($this->options),
        };

        return [$method, $options];
    }

    private static function parseAuthority(string $url): string
    {
        if ($parts = parse_url($url)) {
            $host = $parts['host'] ?? null;
            $port = $parts['port'] ?? null;
            if ($host) {
                return $port ? "{$host}:{$port}" : $host;
            }
        }
        throw new TransportException(sprintf('Invalid URI: %s', $url));
    }

    private static function filterOptions(array $options): array
    {
        unset($options['auth_basic'], $options['auth_bearer']);
        foreach ($options['headers'] ?? [] as $key => $value) {
            if (\is_string($key)) {
                if (strcasecmp('authorization', $key) === 0) {
                    unset($options['headers'][$key]);
                }
            } else {
                $header = (string)$value;
                if (stripos($header, 'authorization:') === 0) {
                    unset($options['headers'][$key]);
                }
            }
        }

        return $options;
    }
}
