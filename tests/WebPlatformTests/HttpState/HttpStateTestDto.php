<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\WebPlatformTests\HttpState;

final class HttpStateTestDto implements \Stringable
{
    public function __construct(
        public string $id,
        public string $uri,
        public array $setCookie,
        public string $redirectUri,
        public string $expected,
        public ?string $skip = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
