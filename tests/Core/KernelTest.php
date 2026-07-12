<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Environment;
use OezCMS\Core\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    private string $basePath;
    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir() . '/oezcms-kernel-test-' . uniqid();
        if (!mkdir($this->basePath, 0777, true) && !is_dir($this->basePath)) {
            self::fail('Unable to create temporary test directory.');
        }

        $this->envFile = $this->basePath . '/.env';
        $contents = "APP_NAME=oezCMS\nDB_HOST=db.example.com\nDB_PORT=3307\nDB_NAME=oezcms\n";
        if (false === file_put_contents($this->envFile, $contents) && !is_file($this->envFile)) {
            self::fail('Unable to create temporary env file.');
        }

        unset(
            $_ENV['APP_NAME'], $_SERVER['APP_NAME'],
            $_ENV['DB_HOST'], $_SERVER['DB_HOST'],
            $_ENV['DB_PORT'], $_SERVER['DB_PORT'],
            $_ENV['DB_NAME'], $_SERVER['DB_NAME'],
        );
    }

    protected function tearDown(): void
    {
        if (is_file($this->envFile)) {
            unlink($this->envFile);
        }

        if (is_dir($this->basePath)) {
            rmdir($this->basePath);
        }

        parent::tearDown();
    }

    public function testBootRegistersEnvironment(): void
    {
        $kernel = new Kernel($this->envFile);
        $container = $kernel->boot();

        $environment = $container->get(Environment::class);

        self::assertSame('oezCMS', $environment->getString('APP_NAME'));
    }
}
