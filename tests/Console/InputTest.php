<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\Input;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function testExtractsCommandName(): void
    {
        $input = Input::fromArgv(['bin/console', 'cache:clear']);

        self::assertSame('cache:clear', $input->command());
    }

    public function testExtractsArgumentsAfterCommand(): void
    {
        $input = Input::fromArgv(['bin/console', 'user:create', 'alice', 'admin']);

        self::assertSame(['alice', 'admin'], $input->arguments());
    }

    public function testCommandIsNullWhenMissing(): void
    {
        $input = Input::fromArgv(['bin/console']);

        self::assertNull($input->command());
    }

    public function testArgumentsAreEmptyWithoutExtras(): void
    {
        $input = Input::fromArgv(['bin/console', 'cache:clear']);

        self::assertSame([], $input->arguments());
    }
}
