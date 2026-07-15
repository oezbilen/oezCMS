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
    private const array ENVIRONMENT_KEYS = [
        'APP_NAME',
        'APP_TITLE',
        'APP_SECRET',
        'FLAG',
        'PORT',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir() . '/oezcms-env-test-' . uniqid();
        if (!mkdir($this->basePath, 0777) && !is_dir($this->basePath)) {
            self::fail('Unable to create temporary test directory.');
        }

        $this->envFile = (string) tempnam($this->basePath, 'env_');
        if (false === file_put_contents($this->envFile, "APP_NAME=oezCMS\n") && !is_file($this->envFile)) {
            self::fail('Unable to create temporary env file.');
        }

        // Reset environment between tests
        $this->clearEnvironment();
    }

    protected function tearDown(): void
    {
        $this->clearEnvironment();

        if (is_file($this->envFile)) {
            unlink($this->envFile);
        }

        if (is_dir($this->basePath)) {
            rmdir($this->basePath);
        }

        parent::tearDown();
    }

    private function clearEnvironment(): void
    {
        foreach (self::ENVIRONMENT_KEYS as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
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

    public function testExportsVariablesToSuperglobals(): void
    {
        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('oezCMS', $_ENV['APP_NAME'] ?? null);
        self::assertSame('oezCMS', $_SERVER['APP_NAME'] ?? null);
    }

    public function testDoesNotOverrideExistingEnvironmentVariables(): void
    {
        $_ENV['APP_NAME'] = 'from-real-env';
        $_SERVER['APP_NAME'] = 'from-real-env';

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('from-real-env', $_ENV['APP_NAME']);
        self::assertSame('from-real-env', $environment->get('APP_NAME'));
    }

    public function testStripsSurroundingQuotesFromValues(): void
    {
        file_put_contents(
            $this->envFile,
            "APP_NAME=\"oezCMS\"\nAPP_TITLE='oez Content'\n",
        );

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('oezCMS', $environment->get('APP_NAME'));
        self::assertSame('oez Content', $environment->get('APP_TITLE'));
    }

    public function testIgnoresLinesWithEmptyKey(): void
    {
        file_put_contents(
            $this->envFile,
            "=orphan\n   =whitespace\nAPP_NAME=oezCMS\n",
        );

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('oezCMS', $environment->get('APP_NAME'));
        self::assertNull($environment->get(''));
        self::assertArrayNotHasKey('', $_ENV);
    }

    public function testStripsInlineComments(): void
    {
        file_put_contents(
            $this->envFile,
            "APP_NAME=oezCMS # inline comment\nAPP_SECRET=abc#123\nAPP_TITLE=\"oez # CMS\"\n",
        );

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('oezCMS', $environment->get('APP_NAME'));
        self::assertSame('abc#123', $environment->get('APP_SECRET'));
        self::assertSame('oez # CMS', $environment->get('APP_TITLE'));
    }

    public function testGetStringReturnsValue(): void
    {
        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('oezCMS', $environment->getString('APP_NAME'));
    }

    public function testGetStringReturnsDefaultWhenKeyIsMissing(): void
    {
        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('fallback', $environment->getString('DOES_NOT_EXIST', 'fallback'));
    }

    public function testGetBoolReturnsTrueForTrueLikeValues(): void
    {
        file_put_contents($this->envFile, "FLAG=true\n");

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertTrue($environment->getBool('FLAG'));
    }

    public function testGetBoolReturnsFalseForFalseLikeValues(): void
    {
        file_put_contents($this->envFile, "FLAG=false\n");

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertFalse($environment->getBool('FLAG', true));
    }

    public function testGetBoolReturnsDefaultForUnrecognizedValue(): void
    {
        file_put_contents($this->envFile, "FLAG=maybe\n");

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertTrue($environment->getBool('FLAG', true));
    }

    public function testGetBoolReturnsDefaultWhenKeyIsMissing(): void
    {
        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertTrue($environment->getBool('DOES_NOT_EXIST', true));
    }

    public function testGetIntReturnsValue(): void
    {
        file_put_contents($this->envFile, "PORT=8080\n");

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame(8080, $environment->getInt('PORT'));
    }

    public function testGetIntReturnsDefaultForNonNumericValue(): void
    {
        file_put_contents($this->envFile, "PORT=not-a-number\n");

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame(3306, $environment->getInt('PORT', 3306));
    }

    public function testGetIntReturnsDefaultWhenKeyIsMissing(): void
    {
        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame(3306, $environment->getInt('DOES_NOT_EXIST', 3306));
    }

    public function testExportsVariablesToProcessEnvironment(): void
    {
        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('oezCMS', getenv('APP_NAME'));
    }

    public function testDoesNotOverrideExistingProcessEnvironment(): void
    {
        putenv('APP_NAME=from-process-env');

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('from-process-env', getenv('APP_NAME'));
        self::assertSame('from-process-env', $environment->get('APP_NAME'));
    }

    public function testStripsInlineCommentAfterQuotedValue(): void
    {
        file_put_contents(
            $this->envFile,
            "APP_NAME=\"oezCMS\" # comment\nAPP_TITLE='oez Content' # note\n",
        );

        $environment = new Environment($this->envFile);
        $environment->load();

        self::assertSame('oezCMS', $environment->get('APP_NAME'));
        self::assertSame('oez Content', $environment->get('APP_TITLE'));
    }
}
