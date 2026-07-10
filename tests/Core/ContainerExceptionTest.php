<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\ContainerException;
use PHPUnit\Framework\TestCase;

final class ContainerExceptionTest extends TestCase
{
    public function testCanBeThrown(): void
    {
        $this->expectException(ContainerException::class);

        throw new ContainerException('service not registered');
    }
}
