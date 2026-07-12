<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\BufferedOutput;
use OezCMS\Console\ExitCode;
use OezCMS\Console\Input;
use OezCMS\Console\VersionCommand;
use PHPUnit\Framework\TestCase;

final class VersionCommandTest extends TestCase
{
    public function testIdentifiesItselfAsVersion(): void
    {
        $command = new VersionCommand();

        self::assertSame('version', $command->name());
        self::assertNotSame('', $command->description());
    }

    public function testWritesNameAndVersion(): void
    {
        $command = new VersionCommand();
        $output = new BufferedOutput();

        $exitCode = $command->run(Input::fromArgv(['bin/console', 'version']), $output);

        self::assertSame(ExitCode::Success, $exitCode);
        self::assertStringContainsString('oezCMS', $output->contents());
    }
}
