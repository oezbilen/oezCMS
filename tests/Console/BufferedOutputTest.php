<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\BufferedOutput;
use PHPUnit\Framework\TestCase;

final class BufferedOutputTest extends TestCase
{
    public function testCollectsWrittenText(): void
    {
        $output = new BufferedOutput();

        $output->write('Hello ');
        $output->write('World');

        self::assertSame('Hello World', $output->contents());
    }

    public function testWriteLineAppendsNewline(): void
    {
        $output = new BufferedOutput();

        $output->writeLine('first');
        $output->writeLine('second');

        self::assertSame("first\nsecond\n", $output->contents());
    }
}
