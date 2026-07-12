<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\ExitCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExitCodeTest extends TestCase
{
    #[DataProvider('exitCodeProvider')]
    public function testBackedValuesMatchShellContract(int $expected, ExitCode $exitCode): void
    {
        self::assertSame($expected, $exitCode->value);
    }

    /**
     * @return iterable<string, array{int, ExitCode}>
     */
    public static function exitCodeProvider(): iterable
    {
        yield 'success' => [0, ExitCode::Success];
        yield 'failure' => [1, ExitCode::Failure];
        yield 'usage' => [2, ExitCode::Usage];
    }
}
