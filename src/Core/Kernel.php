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

    public function __construct(private readonly string $envFile)
    {
    }

    public function boot(): Container
    {
        $environment = new Environment($this->envFile);
        $environment->load();

        $container = new Container();
        $container->instance(Environment::class, $environment);
        $container->instance(Config::class, $this->buildConfig($environment));

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

        return new Config([
            'app' => [
                'env' => $environment->getString('APP_ENV', 'production'),
                'debug' => $environment->getBool('APP_DEBUG'),
            ],
            'database' => $database,
        ]);
    }
}
