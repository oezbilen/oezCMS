<?php

declare(strict_types=1);

namespace OezCMS\Core;

final class Container
{
    /**
     * @var array<class-string, callable(self): object>
     */
    private array $factories = [];

    /**
     * @var array<class-string, object>
     */
    private array $instances = [];

    /**
     * @var array<class-string, true>
     */
    private array $resolving = [];

    /**
     * @template T of object
     *
     * @param class-string<T>   $id
     * @param callable(self): T $factory
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;

        unset($this->instances[$id]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     * @param T               $instance
     */
    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            /** @var T $instance */
            $instance = $this->instances[$id];

            return $instance;
        }

        if (!isset($this->factories[$id])) {
            throw new ContainerException(sprintf('Service not registered: %s', $id));
        }

        if (isset($this->resolving[$id])) {
            throw new ContainerException(sprintf(
                'Circular dependency detected: %s -> %s',
                implode(' -> ', array_keys($this->resolving)),
                $id,
            ));
        }

        $this->resolving[$id] = true;

        try {
            $service = ($this->factories[$id])($this);
        } finally {
            unset($this->resolving[$id]);
        }

        $this->instances[$id] = $service;

        /** @var T $service */
        return $service;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }
}
