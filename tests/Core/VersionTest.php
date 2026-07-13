<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testFullCombinesNameAndVersion(): void
    {
        self::assertSame('oezCMS 0.1.0-dev', Version::full());
    }
}
