# SPP Caching Systems: Technical Deep-Dive

The SPP Framework utilizes a multi-layered caching architecture to ensure high performance across different stages of the application lifecycle.

## 1. Orion Cache (Module Registry)
**Purpose**: Zero-I/O module discovery and dependency resolution.
- **Backend**: Compiled PHP array (`var/cache/modules_<app>.php`).
- **Mechanism**: Parses all module manifests (`module.yml` and `module.xml`) and compiles them into a single file.
- **Activation**: Automatic when `SPP_DEBUG` is false.
- **Documentation**: [Orion Cache](modules/orion-cache.md)

## 2. Edge & Object Caching (`SPPCacheManager` / Cache Tags)
**Purpose**: High-performance HTTP-level cache and invalidation using tags.
- **Backend**: Managed via `\SPPMod\SPPCache\SPPCacheManager` sending HTTP `X-SPP-Cache-Tags`.
- **Mechanism**: Every read from an `SPPEntity` generates cache tags (e.g. `Article_list`, `Article:15`).
- **Invalidation**: Modifying or saving an entity triggers programmatic invalidation of specific tags globally.

## 3. General Object Cache (`\SPP\Cache`)
**Purpose**: Generic key-value storage for application-level and framework-level data.
- **Interface**: `\SPP\Core\CacheInterface`
- **Drivers**:
  - **RedisCache**: High-performance distributed cache (requires Redis extension).
  - **FileCache**: Fallback driver using serialized PHP files in `var/cache`.
- **Usage**:
  ```php
  use SPP\Cache;
  Cache::set('my_key', $complexObject, 3600);
  $data = Cache::get('my_key');
  ```

## 3. Asset Orchestration Cache
**Purpose**: Minimizes HTTP requests by bundling and minifying frontend resources.
- **Mechanism**: The `AssetOrchestrator` hashes the list of requested assets and generates a persistent bundle in `var/assets/`.
- **Cache Invalidation**: Automatically invalidates when the list of assets or their order changes (MD5 hash mismatch).
- **Features**: Basic minification and comment stripping.

## 4. Metadata & Registry Caching (Memory)
**Purpose**: Prevents redundant processing within a single request cycle.
- **Registry (`\SPP\Registry`)**: Stores global state and shared objects in a static memory array.
- **Entity Metadata (`SPPEntity`)**: Caches parsed YAML entity configurations in a static registry to avoid repeated file I/O for the same entity type.
- **Settings Cache (`App::getGlobalSettings`)**: Uses a static variable to cache the `global-settings.yml` data after the first parse.

## 5. Session Caching
**Purpose**: Persistent user state across requests.
- **Drivers**: Standard PHP file-based sessions or high-performance **Redis Session Handler**.
- **Configuration**: Automatically switches to Redis if the `redis` module is enabled and available.

---

## 🛠️ Cache Management

### Clearing Caches
Most caches can be cleared via the **SPP Admin Panel** or manually by removing the contents of the `var/cache/` and `var/assets/` directories.

### Development Mode
When `SPP_DEBUG` is enabled:
- Orion Cache is bypassed.
- Asset Orchestration often performs real-time resolution (depending on implementation).
- The general cache remains active but is often cleared more frequently.

---
[Back to Framework Wiki](index.md)
