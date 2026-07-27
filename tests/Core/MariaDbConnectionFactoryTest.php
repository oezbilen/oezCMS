<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Config;
use OezCMS\Core\DatabaseException;
use OezCMS\Core\MariaDbConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;

final class MariaDbConnectionFactoryTest extends TestCase
{
    public function testBuildsDsnFromConfig(): void
    {
        $config = new Config([
            'database' => [
                'host' => 'db.example.com',
                'port' => 3307,
                'name' => 'oezcms',
                'charset' => 'utf8mb4',
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        self::assertSame(
            'mysql:host=db.example.com;port=3307;dbname=oezcms;charset=utf8mb4',
            $factory->dsn($config),
        );
    }

    public function testDsnUsesDefaultsForMissingValues(): void
    {
        $config = new Config([
            'database' => [
                'name' => 'oezcms',
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        self::assertSame(
            'mysql:host=127.0.0.1;port=3306;dbname=oezcms;charset=utf8mb4',
            $factory->dsn($config),
        );
    }

    public function testThrowsWhenDatabaseNameIsMissing(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn(new Config([]));
    }

    public function testThrowsWhenDatabaseNameIsEmpty(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn(new Config(['database' => ['name' => '']]));
    }

    public function testOptionsDisableMultiStatementExecution(): void
    {
        $options = (new MariaDbConnectionFactory())->options(new Config([]));

        self::assertArrayHasKey(PDO::MYSQL_ATTR_MULTI_STATEMENTS, $options);
        self::assertFalse($options[PDO::MYSQL_ATTR_MULTI_STATEMENTS]);
    }

    public function testOptionsAppendOnlyFullGroupByToSessionSqlMode(): void
    {
        $options = (new MariaDbConnectionFactory())->options(new Config([]));

        self::assertArrayHasKey(PDO::MYSQL_ATTR_INIT_COMMAND, $options);

        $command = $options[PDO::MYSQL_ATTR_INIT_COMMAND];

        self::assertIsString($command);
        self::assertStringContainsString('CONCAT_WS', $command);
        self::assertStringContainsString('@@SESSION.sql_mode', $command);
        self::assertStringContainsString('ONLY_FULL_GROUP_BY', $command);
    }

    public function testOptionsKeepSecurePdoDefaults(): void
    {
        $options = (new MariaDbConnectionFactory())->options(new Config([]));

        self::assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        self::assertSame(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
        self::assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
        self::assertFalse($options[PDO::ATTR_STRINGIFY_FETCHES]);
    }

    public function testReturnsConfiguredUsername(): void
    {
        $config = new Config(['database' => ['username' => 'oezcms_runtime']]);

        self::assertSame('oezcms_runtime', (new MariaDbConnectionFactory())->username($config));
    }

    public function testThrowsWhenUsernameIsMissing(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->username(new Config([]));
    }

    public function testThrowsWhenUsernameIsEmpty(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->username(new Config(['database' => ['username' => '']]));
    }

    public function testReturnsConfiguredPassword(): void
    {
        $config = new Config(['database' => ['password' => 'secret']]);

        self::assertSame('secret', (new MariaDbConnectionFactory())->password($config));
    }

    public function testAllowsExplicitlyEmptyPassword(): void
    {
        // An empty password is legitimate under socket authentication; an
        // absent one is not. Only the missing key may be rejected, otherwise
        // the safest setup would be the one configuration that is forbidden.
        $config = new Config(['database' => ['password' => '']]);

        self::assertSame('', (new MariaDbConnectionFactory())->password($config));
    }

    public function testThrowsWhenPasswordIsMissing(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->password(new Config([]));
    }

    public function testMigrationCredentialsFallBackToTheRuntimeCredentials(): void
    {
        $config = new Config([
            'database' => ['username' => 'oezcms_runtime', 'password' => 'runtime-secret'],
        ]);

        $factory = new MariaDbConnectionFactory();

        self::assertSame('oezcms_runtime', $factory->migrationUsername($config));
        self::assertSame('runtime-secret', $factory->migrationPassword($config));
    }

    public function testUsesConfiguredMigrationCredentials(): void
    {
        $config = new Config([
            'database' => [
                'username' => 'oezcms_runtime',
                'password' => 'runtime-secret',
                'migration' => ['username' => 'oezcms_deploy', 'password' => 'deploy-secret'],
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        self::assertSame('oezcms_deploy', $factory->migrationUsername($config));
        self::assertSame('deploy-secret', $factory->migrationPassword($config));
    }

    public function testAllowsExplicitlyEmptyMigrationPassword(): void
    {
        $config = new Config([
            'database' => [
                'username' => 'oezcms_runtime',
                'password' => 'runtime-secret',
                'migration' => ['username' => 'oezcms_deploy', 'password' => ''],
            ],
        ]);

        self::assertSame('', (new MariaDbConnectionFactory())->migrationPassword($config));
    }

    public function testRejectsMigrationUsernameWithoutPassword(): void
    {
        $config = new Config([
            'database' => [
                'username' => 'oezcms_runtime',
                'password' => 'runtime-secret',
                'migration' => ['username' => 'oezcms_deploy'],
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->migrationUsername($config);
    }

    public function testRejectsMigrationPasswordWithoutUsername(): void
    {
        $config = new Config([
            'database' => [
                'username' => 'oezcms_runtime',
                'password' => 'runtime-secret',
                'migration' => ['password' => 'deploy-secret'],
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->migrationPassword($config);
    }

    public function testRejectsEmptyMigrationUsername(): void
    {
        $config = new Config([
            'database' => [
                'username' => 'oezcms_runtime',
                'password' => 'runtime-secret',
                'migration' => ['username' => '', 'password' => 'deploy-secret'],
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->migrationUsername($config);
    }

    public function testRejectsUnsupportedCharset(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn(new Config(['database' => ['name' => 'oezcms', 'charset' => 'latin1']]));
    }

    public function testRejectsPortBelowTheValidRange(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn(new Config(['database' => ['name' => 'oezcms', 'port' => 0]]));
    }

    public function testRejectsPortAboveTheValidRange(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn(new Config(['database' => ['name' => 'oezcms', 'port' => 70000]]));
    }

    public function testRejectsEmptyHost(): void
    {
        // getString only falls back for a missing or non-string value, so an
        // explicitly empty DB_HOST reached the DSN as "host=".
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn(new Config(['database' => ['name' => 'oezcms', 'host' => '']]));
    }

    public function testBuildsSocketDsn(): void
    {
        $config = new Config([
            'database' => ['name' => 'oezcms', 'socket' => '/run/mysqld/mysqld.sock'],
        ]);

        self::assertSame(
            'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=oezcms;charset=utf8mb4',
            (new MariaDbConnectionFactory())->dsn($config),
        );
    }

    public function testRejectsSocketCombinedWithHost(): void
    {
        // Host and socket are different transports. Preferring one silently
        // would leave the other in the file looking effective.
        $config = new Config([
            'database' => [
                'name' => 'oezcms',
                'socket' => '/run/mysqld/mysqld.sock',
                'host' => 'db.example.com',
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn($config);
    }

    public function testRejectsSocketCombinedWithPort(): void
    {
        $config = new Config([
            'database' => [
                'name' => 'oezcms',
                'socket' => '/run/mysqld/mysqld.sock',
                'port' => 3307,
            ],
        ]);

        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn($config);
    }

    public function testRejectsEmptySocket(): void
    {
        $config = new Config(['database' => ['name' => 'oezcms', 'socket' => '']]);

        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->dsn($config);
    }

    public function testAppliesTlsCertificateAuthority(): void
    {
        $config = new Config(['database' => ['ssl' => ['ca' => '/etc/ssl/mariadb-ca.pem']]]);

        $options = (new MariaDbConnectionFactory())->options($config);

        self::assertArrayHasKey(PDO::MYSQL_ATTR_SSL_CA, $options);
        self::assertSame('/etc/ssl/mariadb-ca.pem', $options[PDO::MYSQL_ATTR_SSL_CA]);
    }

    public function testVerifiesTheServerCertificateWhenACertificateAuthorityIsConfigured(): void
    {
        // Encryption without server verification protects against nobody who is
        // in a position to attack the connection in the first place.
        $config = new Config(['database' => ['ssl' => ['ca' => '/etc/ssl/mariadb-ca.pem']]]);

        $options = (new MariaDbConnectionFactory())->options($config);

        self::assertArrayHasKey(PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT, $options);
        self::assertTrue($options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]);
    }

    public function testAppliesClientCertificateAndKey(): void
    {
        $config = new Config([
            'database' => [
                'ssl' => [
                    'ca' => '/etc/ssl/mariadb-ca.pem',
                    'cert' => '/etc/ssl/client-cert.pem',
                    'key' => '/etc/ssl/client-key.pem',
                ],
            ],
        ]);

        $options = (new MariaDbConnectionFactory())->options($config);

        self::assertArrayHasKey(PDO::MYSQL_ATTR_SSL_CERT, $options);
        self::assertArrayHasKey(PDO::MYSQL_ATTR_SSL_KEY, $options);
        self::assertSame('/etc/ssl/client-cert.pem', $options[PDO::MYSQL_ATTR_SSL_CERT]);
        self::assertSame('/etc/ssl/client-key.pem', $options[PDO::MYSQL_ATTR_SSL_KEY]);
    }

    public function testRejectsClientCertificateWithoutKey(): void
    {
        $config = new Config([
            'database' => ['ssl' => ['ca' => '/etc/ssl/mariadb-ca.pem', 'cert' => '/etc/ssl/client-cert.pem']],
        ]);

        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->options($config);
    }

    public function testRejectsClientCertificateWithoutCertificateAuthority(): void
    {
        // Presenting a client certificate to a server nobody verified
        // authenticates the wrong direction.
        $config = new Config([
            'database' => ['ssl' => ['cert' => '/etc/ssl/client-cert.pem', 'key' => '/etc/ssl/client-key.pem']],
        ]);

        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->options($config);
    }

    public function testAppliesConnectTimeout(): void
    {
        $options = (new MariaDbConnectionFactory())->options(new Config(['database' => ['connect_timeout' => 5]]));

        self::assertArrayHasKey(PDO::ATTR_TIMEOUT, $options);
        self::assertSame(5, $options[PDO::ATTR_TIMEOUT]);
    }

    public function testRejectsConnectTimeoutOutsideTheValidRange(): void
    {
        $factory = new MariaDbConnectionFactory();

        $this->expectException(DatabaseException::class);

        $factory->options(new Config(['database' => ['connect_timeout' => 0]]));
    }

    public function testOmitsConnectionOptionsThatAreNotConfigured(): void
    {
        $options = (new MariaDbConnectionFactory())->options(new Config([]));

        self::assertArrayNotHasKey(PDO::MYSQL_ATTR_SSL_CA, $options);
        self::assertArrayNotHasKey(PDO::MYSQL_ATTR_SSL_CERT, $options);
        self::assertArrayNotHasKey(PDO::MYSQL_ATTR_SSL_KEY, $options);
        self::assertArrayNotHasKey(PDO::ATTR_TIMEOUT, $options);
    }
}
