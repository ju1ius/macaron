<?php declare(strict_types=1);

namespace Souplette\Macaron\Bridge\Symfony;

use Souplette\Macaron\Http\HttpMethod;
use Souplette\Macaron\Internal\Str;

/**
 * @internal
 */
final class ClientState
{
    private array $filteredOptions;

    public function __construct(
        private array $options,
    ) {
    }

    public function redirect(HttpMethod $method, int $code, bool $sameOrigin): array
    {
        if (\in_array($code, [301, 302, 303], true)) {
            switch ($method) {
                case HttpMethod::Get:
                case HttpMethod::Head:
                    break;
                default:
                    $method = HttpMethod::Get;
                    unset($this->options['query'], $this->options['body']);
                    unset($this->filteredOptions['query'], $this->filteredOptions['body']);
                    break;
            }
        }

        $options = match ($sameOrigin) {
            true => $this->options,
            false => $this->filteredOptions ??= self::filterOptions($this->options),
        };

        return [$method, $options];
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
                if (Str::iStartsWith($header, 'authorization:')) {
                    unset($options['headers'][$key]);
                }
            }
        }

        return $options;
    }
}
