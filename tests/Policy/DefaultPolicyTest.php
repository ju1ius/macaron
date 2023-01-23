<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Policy;

use ju1ius\Macaron\Policy\CookiePolicyInterface;
use ju1ius\Macaron\Policy\DefaultPolicy;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

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
