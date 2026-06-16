<?php

namespace SPP\Core;

use Psr\Container\ContainerInterface;
use SPP\SPPException;

/**
 * class \SPP\Core\Container
 *
 * A PSR-11 compliant dependency injection container.
 * Supports singletons, factories, and interface binding.
 */
class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private static array $reflectorCache = [];

    /**
     * Bind a service to the container.
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared'   => $shared
        ];
    }

    /**
     * Bind a singleton service to the container.
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Get a service from the container.
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!$this->has($id)) {
            // Attempt to resolve if it's a class name
            if (class_exists($id)) {
                return $this->resolve($id);
            }
            throw new SPPException("Service not found: " . $id);
        }

        $concrete = $this->bindings[$id]['concrete'];
        $object = $this->resolve($concrete);

        if ($this->bindings[$id]['shared']) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    /**
     * Check if a service exists in the container.
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Resolve a concrete type.
     */
    private function resolve($concrete)
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }

        if (is_object($concrete)) {
            return $concrete;
        }

        if (isset(self::$reflectorCache[$concrete])) {
            $reflector = self::$reflectorCache[$concrete]['reflector'];
            $parameters = self::$reflectorCache[$concrete]['parameters'];
            if ($parameters === null) {
                return new $concrete();
            }
        } else {
            $reflector = new \ReflectionClass($concrete);

            if (!$reflector->isInstantiable()) {
                throw new SPPException("Class {$concrete} is not instantiable.");
            }

            $constructor = $reflector->getConstructor();
            $parameters = $constructor ? $constructor->getParameters() : null;

            self::$reflectorCache[$concrete] = [
                'reflector' => $reflector,
                'parameters' => $parameters
            ];

            if ($parameters === null) {
                return new $concrete();
            }
        }

        $dependencies = $this->resolveDependencies($parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve dependencies for a constructor.
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (!$type) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new SPPException("Cannot resolve primitive dependency: {$parameter->name}");
            }

            $dependencies[] = $this->resolveTypedDependency($type, $parameter);
        }

        return $dependencies;
    }

    /**
     * Resolve a reflected type declaration.
     */
    private function resolveTypedDependency(\ReflectionType $type, \ReflectionParameter $parameter): mixed
    {
        if ($type instanceof \ReflectionNamedType) {
            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }
                if ($type->allowsNull()) {
                    return null;
                }
                throw new SPPException("Cannot resolve primitive dependency: {$parameter->name}");
            }

            return $this->get($type->getName());
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof \ReflectionNamedType && !$unionType->isBuiltin()) {
                    try {
                        return $this->get($unionType->getName());
                    } catch (\Throwable $e) {
                        // Try the next class in the union before failing below.
                    }
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            if ($type->allowsNull()) {
                return null;
            }
        }

        throw new SPPException("Cannot resolve dependency: {$parameter->name}");
    }
}
