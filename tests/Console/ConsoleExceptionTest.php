<?php

declare(strict_types=1);

namespace OezCMS\Tests\Console;

use OezCMS\Console\ConsoleException;
use PHPUnit\Framework\TestCase;

final class ConsoleExceptionTest extends TestCase
{
    public function testCanBeThrown(): void
    {
        $this->expectException(ConsoleException::class);

        throw new ConsoleException('console error');
    }
}
