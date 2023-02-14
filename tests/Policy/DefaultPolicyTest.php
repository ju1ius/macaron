<?php declare(strict_types=1);

namespace Souplette\Macaron\Tests\Policy;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Souplette\Macaron\Policy\CookiePolicyInterface;
use Souplette\Macaron\Policy\DefaultPolicy;

final class DefaultPolicyTest extends TestCase
{
    public function testDefaults(): void
    {
        $policy = new DefaultPolicy();
        Assert::assertSame(CookiePolicyInterface::RECOMMENDED_MAX_EXPIRY, $policy->getMaxExpiry());
        Assert::assertSame(\PHP_INT_MAX, $policy->getMaxCount());
        Assert::assertSame(\PHP_INT_MAX, $policy->getMaxCountPerDomain());
        Assert::assertFalse($policy->isAllowingPublicSuffixes());
    }
}
