<?php

declare(strict_types=1);

namespace OezCMS\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TestDatabaseGuardTest extends TestCase
{
    public function testAcceptsDatabasesNamedAsTestDatabases(): void
    {
        self::assertSame([], TestDatabaseGuard::violations('oezcms_test', 'oezcms_test_log', []));
    }

    public function testAcceptsTestDatabasesBesideTheApplicationDatabases(): void
    {
        self::assertSame([], TestDatabaseGuard::violations('oezcms_test', 'oezcms_test_log', [
            'DB_NAME' => 'oezcms',
            'DB_MIGRATION_LOG_DATABASE' => 'oezcms_log',
        ]));
    }

    #[DataProvider('unsafeNameProvider')]
    public function testRefusesADatabaseNotNamedAsATestDatabase(string $name, string $logName, string $expected): void
    {
        self::assertSame([$expected], TestDatabaseGuard::violations($name, $logName, []));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unsafeNameProvider(): iterable
    {
        yield 'the application database' => [
            'oezcms',
            'oezcms_test_log',
            'TEST_DB_NAME "oezcms" is not a test database name (lower case, ending in "_test").',
        ];

        yield 'the suffix inside the name' => [
            'oezcms_test_data',
            'oezcms_test_log',
            'TEST_DB_NAME "oezcms_test_data" is not a test database name (lower case, ending in "_test").',
        ];

        yield 'the suffix alone' => [
            '_test',
            'oezcms_test_log',
            'TEST_DB_NAME "_test" is not a test database name (lower case, ending in "_test").',
        ];

        yield 'upper case' => [
            'OEZCMS_TEST',
            'oezcms_test_log',
            'TEST_DB_NAME "OEZCMS_TEST" is not a test database name (lower case, ending in "_test").',
        ];

        yield 'a trailing newline' => [
            "oezcms_test\n",
            'oezcms_test_log',
            "TEST_DB_NAME \"oezcms_test\n\" is not a test database name (lower case, ending in \"_test\").",
        ];

        yield 'the application log database' => [
            'oezcms_test',
            'oezcms_log',
            'TEST_DB_LOG_NAME "oezcms_log" is not a test database name (lower case, ending in "_test_log").',
        ];

        yield 'the test database as log database' => [
            'oezcms_test',
            'oezcms_test',
            'TEST_DB_LOG_NAME "oezcms_test" is not a test database name (lower case, ending in "_test_log").',
        ];
    }

    public function testRefusesATestDatabaseTheApplicationIsConfiguredToUse(): void
    {
        self::assertSame(
            ['TEST_DB_NAME "oezcms_test" is the database DB_NAME names.'],
            TestDatabaseGuard::violations('oezcms_test', 'oezcms_test_log', ['DB_NAME' => 'oezcms_test']),
        );
    }

    public function testRefusesALogDatabaseTheApplicationIsConfiguredToUse(): void
    {
        self::assertSame(
            ['TEST_DB_LOG_NAME "oezcms_test_log" is the database DB_MIGRATION_LOG_DATABASE names.'],
            TestDatabaseGuard::violations('oezcms_test', 'oezcms_test_log', ['DB_MIGRATION_LOG_DATABASE' => 'oezcms_test_log']),
        );
    }

    public function testReportsEveryViolation(): void
    {
        self::assertSame(
            [
                'TEST_DB_NAME "oezcms" is not a test database name (lower case, ending in "_test").',
                'TEST_DB_LOG_NAME "oezcms_log" is not a test database name (lower case, ending in "_test_log").',
                'TEST_DB_NAME "oezcms" is the database DB_NAME names.',
                'TEST_DB_LOG_NAME "oezcms_log" is the database DB_MIGRATION_LOG_DATABASE names.',
            ],
            TestDatabaseGuard::violations('oezcms', 'oezcms_log', [
                'DB_NAME' => 'oezcms',
                'DB_MIGRATION_LOG_DATABASE' => 'oezcms_log',
            ]),
        );
    }
}
