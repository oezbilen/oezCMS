<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Environment;
use OezCMS\Core\EnvironmentException;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    private string $basePath;
    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir() . '/oezcms-env-test-' . uniqid();
        if (!mkdir($this->basePath, 0777) && !is_dir($this->basePath)) {
            $this->fail('Unable to create temporary test directory.');
        }

        $this->envFile = (string) tempnam($this->basePath, 'env_');
        if (!file_put_contents($this->envFile, "APP_NAME=oezCMS\n") && !is_file($this->envFile)) {
            $this->fail('Unable to create temporary env file.');
        }

        // Reset environment between tests
        unset($_ENV['APP_NAME']);
        unset($_SERVER['APP_NAME']);
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

    public function testLoadsEnvironmentFile(): void
    {
        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('oezCMS', $environment->get('APP_NAME'));
    }

    public function testThrowsExceptionWhenFileDoesNotExist(): void
    {
        $environment = new Environment($this->basePath . '/does-not-exist.env');

        $this->expectException(EnvironmentException::class);

        $environment->load();
    }

    public function testIgnoresCommentLines(): void
    {
        file_put_contents(
            $this->envFile,
            "# a full comment line\n#APP_ENV=production\nAPP_NAME=oezCMS\n",
        );

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('oezCMS', $environment->get('APP_NAME'));
        self::assertNull($environment->get('#APP_ENV'));
    }
}
