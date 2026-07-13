<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\StreamOutput;
use PHPUnit\Framework\TestCase;

final class StreamOutputTest extends TestCase
{
    public function testWritesToGivenStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $output = new StreamOutput($stream);
        $output->write('Hello ');
        $output->writeLine('World');

        rewind($stream);
        self::assertSame("Hello World\n", stream_get_contents($stream));
    }
}
