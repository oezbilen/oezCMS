<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\StandardOutput;
use PHPUnit\Framework\TestCase;

final class StandardOutputTest extends TestCase
{
    public function testWriteSendsTextToStandardOutput(): void
    {
        $this->expectOutputString('hello');

        (new StandardOutput())->write('hello');
    }

    public function testWriteLineAppendsNewline(): void
    {
        $this->expectOutputString("hello\n");

        (new StandardOutput())->writeLine('hello');
    }
}
