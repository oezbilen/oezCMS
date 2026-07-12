<?php

declare(strict_types=1);

namespace OezCMS\Core;

final class Kernel
{
    public function __construct(private readonly string $envFile)
    {
    }

    public function boot(): Container
    {
        $environment = new Environment($this->envFile);
        $environment->load();

        $container = new Container();
        $container->instance(Environment::class, $environment);

        return $container;
    }
}
