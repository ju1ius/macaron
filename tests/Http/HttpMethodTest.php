<?php declare(strict_types=1);

namespace ju1ius\Macaron\Tests\Http;

use ju1ius\Macaron\Http\HttpMethod;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpMethodTest extends TestCase
{
    public static function testOfIdentity(): void
    {
        $input = HttpMethod::Post;
        Assert::assertSame($input, HttpMethod::of($input));
    }

    #[DataProvider('ofProvider')]
    public function testOf(string $input, ?HttpMethod $expected): void
    {
        if ($expected === null) {
            $this->expectException(\ValueError::class);
        }
        Assert::assertSame($expected, HttpMethod::of($input));
    }

    public static function ofProvider(): iterable
    {
        yield ['Get', HttpMethod::Get];
        yield ['HeAd', HttpMethod::Head];
        yield ['options', HttpMethod::Options];
        yield ['trace', HttpMethod::Trace];
        yield ['pOst', HttpMethod::Post];
        yield ['pUt', HttpMethod::Put];
        yield ['patch', HttpMethod::Patch];
        yield ['DELETE', HttpMethod::Delete];
        yield ['CoNNeCt', HttpMethod::Connect];
        yield ['nope', null];
        yield ['', null];
    }

    #[DataProvider('isSafeProvider')]
    public function testIsSafe(HttpMethod $method, bool $expected): void
    {
        Assert::assertSame($expected, $method->isSafe());
    }

    public static function isSafeProvider(): iterable
    {
        yield [HttpMethod::Get, true];
        yield [HttpMethod::Head, true];
        yield [HttpMethod::Options, true];
        yield [HttpMethod::Trace, true];
        yield [HttpMethod::Post, false];
        yield [HttpMethod::Put, false];
        yield [HttpMethod::Patch, false];
        yield [HttpMethod::Delete, false];
        yield [HttpMethod::Connect, false];
    }

    #[DataProvider('isIdemPotentProvider')]
    public function testIsIdemPotent(HttpMethod $method, bool $expected): void
    {
        Assert::assertSame($expected, $method->isIdemPotent());
    }

    public static function isIdemPotentProvider(): iterable
    {
        yield [HttpMethod::Get, true];
        yield [HttpMethod::Head, true];
        yield [HttpMethod::Put, true];
        yield [HttpMethod::Delete, true];
        yield [HttpMethod::Options, true];
        yield [HttpMethod::Trace, true];
        yield [HttpMethod::Post, false];
        yield [HttpMethod::Patch, false];
        yield [HttpMethod::Connect, false];
    }
}
