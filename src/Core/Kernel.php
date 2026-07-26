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
                'env' => $environment->getString('APP_ENV', 'production'),
                'debug' => $environment->getBool('APP_DEBUG'),
            ],
            'database' => $database,
        ]);
    }
}
