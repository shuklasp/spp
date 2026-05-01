# Core Component: Registry

The `\SPP\Registry` is a high-performance, hierarchical global state manager for the SPP framework. It serves as a centralized store for configurations, directories, and application-level variables.

## 1. Hierarchical Storage
The Registry uses a tokenized key system (`=>`) to manage nested data structures efficiently.

**Example:**
```php
Registry::register('app=>config=>db=>host', 'localhost');
$host = Registry::get('app=>config=>db=>host');
```

## 2. Context Isolation
One of the most powerful features of the Registry is its automatic context scoping. When an application (e.g., Lekhak) is active, the Registry automatically prefixes keys to prevent state collision between different apps.

*   **Global Keys**: Keys starting with `__` are global across the entire framework.
*   **Scoped Keys**: Standard keys are automatically mapped to the current app context (e.g., `lekhak=>my_key`).
*   **Shared Polyglot Keys**: Keys starting with `__shared=>` are automatically persisted to disk for cross-process and multi-language communication.

## 3. Polyglot & Shared Persistence
For interoperability with other languages (Python, Go, Node.js), the Registry implements a hybrid persistence model.

*   **Logic**: Any key registered under the `__shared=>` namespace is immediately synchronized to a JSON file.
*   **Storage**: `var/shared/registry.json`
*   **Automation**: The framework automatically loads this shared state at the beginning of every request via `Registry::loadShared()`.

**Usage:**
```php
// Accessible by PHP, Python, Go, etc.
Registry::register('__shared=>site_status', 'maintenance');
```
The Registry now acts as a static facade for the [Service Container](container.md). This allows for a unified interface to manage both configuration data and complex services.

**Key Methods:**
*   `bind(string $abstract, $concrete)`: Register a service.
*   `singleton(string $abstract, $concrete)`: Register a shared service.
*   `make(string $abstract)`: Resolve a service via the container.

## 4. Performance
The Registry implements an O(1) flat lookup cache, ensuring that retrieving even deeply nested keys is nearly instantaneous during the request lifecycle.

---
[Back to Index](index.md) | [Service Container](container.md)
