<?php declare(strict_types=1);

namespace Souplette\Macaron\Benchmarks;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputMode;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\RetryThreshold;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;

#[RetryThreshold(3.0)]
#[OutputTimeUnit('seconds')]
#[OutputMode('throughput')]
final class StringBench
{
    #[Subject]
    #[Iterations(10)]
    #[Revs(100)]
    #[ParamProviders('provideIsAscii')]
    public function isAsciiPcre(array $args): void
    {
        $input = $args[0];
        $isAscii = !$input || preg_match('/^[\x00-\x7F]+$/', $input);
    }
    #[Subject]
    #[Iterations(10)]
    #[Revs(100)]
    #[ParamProviders('provideIsAscii')]
    public function isAsciiMbString(array $args): void
    {
        $input = $args[0];
        $isAscii = !$input || mb_check_encoding($input, 'US-ASCII');
    }

    public static function provideIsAscii(): iterable
    {
        yield 'empty string' => [''];
        yield 'short ASCII string' => ["\x00abcdef\x7F"];
        yield 'long ASCII string' => [
            str_repeat(pack('C*', ...range(0x00, 0x7F)), 10),
        ];
        yield 'short unicode string' => ["àèçéë"];
        yield 'long unicode string' => [
            str_repeat("\x00abcdefàèçéëghijkl\x7F", 10),
        ];
    }
}
