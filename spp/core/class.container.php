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
            throw new SPPException("Service not found: " . $id);
        }

        // If not explicitly bound, it was auto-wired via class or manifest
        if (!isset($this->bindings[$id])) {
            if (class_exists($id)) {
                $reflector = new \ReflectionClass($id);
                if ($reflector->isInstantiable()) {
                    return $this->resolve($id);
                }
            }
            $manifestClass = $this->resolveManifestImplementation($id);
            if ($manifestClass !== null) {
                return $this->resolve($manifestClass);
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
     * Check if a service exists in the container or can be auto-wired.
     */
    public function has(string $id): bool
    {
        if (isset($this->bindings[$id]) || isset($this->instances[$id])) {
            return true;
        }

        if (class_exists($id)) {
            $reflector = new \ReflectionClass($id);
            if ($reflector->isInstantiable()) {
                return true;
            }
        }

        return $this->resolveManifestImplementation($id) !== null;
    }

    /**
     * Resolves an interface or contract to a concrete class using manifest attributes
     * or declared class reflection without manual service provider bindings.
     */
    private function resolveManifestImplementation(string $id): ?string
    {
        // 1. Check in-memory declared classes
        foreach (get_declared_classes() as $declared) {
            if (is_subclass_of($declared, $id)) {
                $ref = new \ReflectionClass($declared);
                if ($ref->isInstantiable()) {
                    $this->bind($id, $declared);
                    return $declared;
                }
            }
        }

        // 2. Consult FilePropsManager cached properties or compiled file_props.cache.php
        $filesMeta = [];
        if (class_exists('\\SPPMod\\SPPStorage\\FilePropsManager')) {
            $filesMeta = \SPPMod\SPPStorage\FilePropsManager::getCachedProps();
        }
        if (empty($filesMeta)) {
            $base = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2);
            $cacheFile = $base . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'file_props.cache.php';
            if (file_exists($cacheFile)) {
                $cached = @include $cacheFile;
                $filesMeta = $cached['files'] ?? [];
            }
        }

        $idShort = substr($id, strrpos($id, '\\') !== false ? strrpos($id, '\\') + 1 : 0);
        $idClean = ltrim($id, '\\');

        foreach ($filesMeta as $filePath => $meta) {
            $attrs = $meta['attributes'] ?? [];
            $implements = (array)($attrs['implements'] ?? ($attrs['binds'] ?? ($attrs['contract'] ?? [])));
            $isSingleton = !empty($attrs['singleton']);

            $matches = false;
            foreach ($implements as $imp) {
                $impClean = ltrim((string)$imp, '\\');
                if ($impClean === $idClean || $imp === $idShort || str_ends_with($idClean, $impClean)) {
                    $matches = true;
                    break;
                }
            }

            $candidateClass = $attrs['class'] ?? null;
            if (!$candidateClass && !empty($meta['usage']) && preg_match('/(?:class:|Loaded via autoloader:)\s*([A-Za-z0-9_\\\\]+)/', $meta['usage'], $m)) {
                $candidateClass = $m[1];
            }

            if (!$candidateClass && str_ends_with($filePath, '.php')) {
                $baseName = basename($filePath, '.php');
                if (str_starts_with($baseName, 'class.')) {
                    $baseName = substr($baseName, 6);
                }
                if (class_exists($baseName)) {
                    $candidateClass = $baseName;
                }
            }

            if ($candidateClass && class_exists($candidateClass)) {
                if ($matches || is_subclass_of($candidateClass, $id)) {
                    $ref = new \ReflectionClass($candidateClass);
                    if ($ref->isInstantiable()) {
                        $this->bind($id, $candidateClass, $isSingleton);
                        return $candidateClass;
                    }
                }
            }
        }

        return null;
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
