<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\WebPlatformTests;

final class HttpCookieTestDTO implements \Stringable
{
    public array $setCookie;

    public function __construct(
        public string $name,
        array|string $setCookie,
        public string $expected,
        public bool $defaultPath = true,
        public ?string $skip = null,
    ) {
        $this->setCookie = \is_array($setCookie) ? $setCookie : [$setCookie];
    }

    public static function fromJson(array $data): self
    {
        return new self(
            $data['name'],
            $data['cookie'],
            $data['expected'],
            $data['defaultPath'] ?? true,
        );
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
