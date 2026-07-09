<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\EnvironmentException;
use PHPUnit\Framework\TestCase;

final class EnvironmentExceptionTest extends TestCase
{
    public function testCanBeThrown(): void
    {
        $this->expectException(EnvironmentException::class);

        throw new EnvironmentException('test error');
    }
}
