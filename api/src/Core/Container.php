<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Container
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function singleton(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * @template T
     * @param class-string<T> $abstract
     * @return T
     */
    public function get(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $instance = ($this->bindings[$abstract])();
            $this->instances[$abstract] = $instance;

            return $instance;
        }

        if (!class_exists($abstract)) {
            throw new \RuntimeException("No binding found for: {$abstract}");
        }

        $reflection = new \ReflectionClass($abstract);
        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("Cannot autowire: {$abstract}");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            $instance = new $abstract();
            $this->instances[$abstract] = $instance;

            return $instance;
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new \RuntimeException('Cannot autowire parameter $' . $parameter->getName() . " for {$abstract}");
            }

            $arguments[] = $this->get($type->getName());
        }

        $instance = $reflection->newInstanceArgs($arguments);
        $this->instances[$abstract] = $instance;

        return $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }
}
