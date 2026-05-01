# Orion Cache: High-Performance Module Registry

**Orion** is the high-performance module registry engine for the SPP Framework. It is designed to minimize I/O overhead during framework bootstrapping by compiling distributed module manifests into a single, optimized PHP cache file.

## 🚀 Overview

In a typical modular application, the framework must scan multiple directories and parse numerous YAML or XML manifests to discover active modules, their dependencies, and registered services. This process can become a bottleneck as the system grows.

The **Orion Cache** resolves this by performing a one-time "compilation" of all active modules into a flat PHP array. This allows for **zero-I/O bootstrapping** in production environments.

## 🛠️ How it Works

1. **Discovery**: Orion scans the system and application module directories to identify all active modules.
2. **Topological Sort**: It resolves the dependency graph between modules to ensure they are loaded in the correct order.
3. **Extraction**: It extracts essential metadata, including:
   - Module paths and versions.
   - Required include files.
   - Service definitions.
   - Default configurations.
4. **Serialization**: The resulting registry is written to a PHP file in `var/cache/modules_<appcontext>.php`.

## ⚙️ Usage

The Orion Cache is automatically managed by the `\SPP\Core\ModuleCompiler` class.

### Activation
The cache is automatically utilized when `SPP_DEBUG` is set to `false`. In development mode (`SPP_DEBUG = true`), Orion is bypassed to allow for real-time changes to module manifests without requiring a re-compile.

### Manual Compilation
You can manually trigger a re-compilation of the Orion cache using the following PHP code:

```php
$compiler = new \SPP\Core\ModuleCompiler($appContext);
$compiler->compile();
```

Or via the **SPP Admin Panel** in the System Information section, where the cache status is displayed.

## 📈 Performance Impact

By moving from distributed manifest parsing to a single file include, Orion typically reduces framework initialization time by **40-60%**, especially in systems with a high number of active modules.

---
[Back to Modules Index](index.md)
