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
    ];

    private const array DATABASE_MIGRATION_KEYS = [
        'DB_MIGRATION_USERNAME' => 'username',
        'DB_MIGRATION_PASSWORD' => 'password',
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

        $database = [];

        foreach (self::DATABASE_STRING_KEYS as $environmentKey => $configKey) {
            if (null !== $environment->get($environmentKey)) {
                $database[$configKey] = $environment->getString($environmentKey);
            }
        }

        if (null !== $environment->get('DB_PORT')) {
            $database['port'] = $environment->getInt('DB_PORT');
        }

        $migration = [];

        foreach (self::DATABASE_MIGRATION_KEYS as $environmentKey => $configKey) {
            if (null !== $environment->get($environmentKey)) {
                $migration[$configKey] = $environment->getString($environmentKey);
            }
        }

        // Written only when something is configured. An empty sub-array would make
        // the factory see a configured credential where there is none.
        if ([] !== $migration) {
            $database['migration'] = $migration;
        }

        return new Config([
            'app' => [
                'env' => $appEnvironment,
                'debug' => $debug,
            ],
            'database' => $database,
        ]);
    }
}
