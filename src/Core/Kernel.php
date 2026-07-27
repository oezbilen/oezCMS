<?php

declare(strict_types=1);

namespace OezCMS\Core;

final class Kernel
{
    private const array DATABASE_STRING_KEYS = [
        'DB_HOST' => 'host',
        'DB_NAME' => 'name',
        'DB_USERNAME' => 'username',
        'DB_PASSWORD' => 'password',
        'DB_CHARSET' => 'charset',
        'DB_SOCKET' => 'socket',
    ];

    private const array DATABASE_INT_KEYS = [
        'DB_PORT' => 'port',
        'DB_CONNECT_TIMEOUT' => 'connect_timeout',
    ];

    /**
     * Nested sections, written only when they carry something. An empty section
     * would announce configured credentials or a configured CA to the factory,
     * which distinguishes all of these by key presence alone.
     */
    private const array DATABASE_SECTIONS = [
        'migration' => [
            'DB_MIGRATION_USERNAME' => 'username',
            'DB_MIGRATION_PASSWORD' => 'password',
        ],
        'ssl' => [
            'DB_SSL_CA' => 'ca',
            'DB_SSL_CERT' => 'cert',
            'DB_SSL_KEY' => 'key',
        ],
    ];

    private const string PRODUCTION_ENVIRONMENT = 'production';

    private const array APP_ENVIRONMENTS = [
        self::PRODUCTION_ENVIRONMENT,
        'staging',
        'development',
        'testing',
    ];

    public function __construct(private readonly string $envFile)
    {
    }

    public function boot(): Container
    {
        $environment = new Environment($this->envFile);

        if (is_file($this->envFile)) {
            $environment->load();
        }

        $container = new Container();
        $container->instance(Environment::class, $environment);
        $container->instance(Config::class, $this->buildConfig($environment));

        $container->set(Database::class, static function (Container $container): Database {
            $factory = new MariaDbConnectionFactory();

            return new Database($factory->create($container->get(Config::class)));
        });

        $container->set(MigrationDatabase::class, static function (Container $container): MigrationDatabase {
            $factory = new MariaDbConnectionFactory();

            return new MigrationDatabase(new Database($factory->createForMigrations($container->get(Config::class))));
        });

        return $container;
    }

    private function buildConfig(Environment $environment): Config
    {
        $appEnvironment = $environment->getString('APP_ENV', self::PRODUCTION_ENVIRONMENT);
        $debug = $environment->getBool('APP_DEBUG');

        if (!in_array($appEnvironment, self::APP_ENVIRONMENTS, true)) {
            throw new EnvironmentException(sprintf(
                'Invalid configuration: APP_ENV must be one of %s, got "%s".',
                implode(', ', self::APP_ENVIRONMENTS),
                $appEnvironment,
            ));
        }

        if (self::PRODUCTION_ENVIRONMENT === $appEnvironment && $debug) {
            throw new EnvironmentException(
                'Invalid configuration: APP_DEBUG must be disabled when APP_ENV is production.',
            );
        }

        $database = $this->stringSection($environment, self::DATABASE_STRING_KEYS);

        foreach (self::DATABASE_INT_KEYS as $environmentKey => $configKey) {
            if (null !== $environment->get($environmentKey)) {
                $database[$configKey] = $environment->getInt($environmentKey);
            }
        }

        foreach (self::DATABASE_SECTIONS as $configKey => $keys) {
            $section = $this->stringSection($environment, $keys);

            if ([] !== $section) {
                $database[$configKey] = $section;
            }
        }

        return new Config([
            'app' => [
                'env' => $appEnvironment,
                'debug' => $debug,
            ],
            'database' => $database,
        ]);
    }

    /**
     * @param  array<string, string> $keys
     * @return array<string, string>
     */
    private function stringSection(Environment $environment, array $keys): array
    {
        $section = [];

        foreach ($keys as $environmentKey => $configKey) {
            if (null !== $environment->get($environmentKey)) {
                $section[$configKey] = $environment->getString($environmentKey);
            }
        }

        return $section;
    }
}
