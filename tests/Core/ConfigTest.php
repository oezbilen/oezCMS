<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testGetStringReturnsValue(): void
    {
        $config = new Config(['app_name' => 'oezCMS']);

        self::assertSame('oezCMS', $config->getString('app_name'));
    }

    public function testGetStringReturnsDefaultWhenKeyIsMissing(): void
    {
        $config = new Config([]);

        self::assertSame('fallback', $config->getString('missing', 'fallback'));
    }

    public function testGetStringReturnsDefaultForNonStringValue(): void
    {
        $config = new Config(['port' => 8080]);

        self::assertSame('', $config->getString('port'));
    }

    public function testGetStringResolvesNestedKeysWithDotNotation(): void
    {
        $config = new Config([
            'app' => [
                'name' => 'oezCMS',
            ],
        ]);

        self::assertSame('oezCMS', $config->getString('app.name'));
    }

    public function testGetStringReturnsDefaultForMissingNestedKey(): void
    {
        $config = new Config([
            'app' => [
                'name' => 'oezCMS',
            ],
        ]);

        self::assertSame('n/a', $config->getString('app.version', 'n/a'));
    }

    public function testGetStringReturnsDefaultWhenTraversingNonArray(): void
    {
        $config = new Config([
            'app' => [
                'name' => 'oezCMS',
            ],
        ]);

        self::assertSame('x', $config->getString('app.name.deep', 'x'));
    }
}
