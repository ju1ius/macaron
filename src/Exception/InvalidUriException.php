<?php declare(strict_types=1);

namespace Souplette\Macaron\Exception;

use Souplette\Macaron\Uri\Origin;
use Souplette\Macaron\Uri\Site;

final class InvalidUriException extends \InvalidArgumentException implements MacaronExceptionInterface
{
    public static function forUri(string $uri): self
    {
        return new self(sprintf('Invalid URI "%s"', $uri));
    }

    public static function invalidOrigin(Origin $origin): self
    {
        return new self(sprintf('Invalid Origin "%s"', $origin));
    }

    public static function invalidSite(Site $site): self
    {
        return new self(sprintf('Invalid Site "%s"', $site));
    }
}
