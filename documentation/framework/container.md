# Core Component: Service Container (DI)

The SPP Container (`\SPP\Core\Container`) is a modern, PSR-11 compliant Dependency Injection (DI) engine. It is the architectural successor to the Registry for managing complex object lifecycles.

## 1. Core Features
*   **PSR-11 Compliant**: Implements `get()` and `has()` for standard service resolution.
*   **Auto-Wiring**: Uses Reflection to automatically resolve and inject dependencies into constructors.
*   **Shared Services (Singletons)**: Supports binding services that persist for the duration of the request.
*   **Factory Closures**: Allows for complex instantiation logic via lazy-loading closures.

## 2. Basic Usage

### Binding a Service
```php
// Simple binding (new instance every time)
Registry::bind(Logger::class, FileLogger::class);

// Singleton binding (reused instance)
Registry::singleton(Database::class, function($container) {
    return new Database(Registry::get('db_config'));
});
```

### Resolving a Service
```php
// Via Registry Facade
$db = Registry::make(Database::class);

// Via App Instance
$container = \SPP\App::getApp()->getContainer();
$mailer = $container->get(Mailer::class);
```

## 3. Advanced: Auto-Wiring
The container automatically resolves dependencies for classes it manages.

```php
class PostService {
    // The container automatically injects Database and Mailer
    public function __construct(Database $db, Mailer $mailer) {
        $this->db = $db;
        $this->mailer = $mailer;
    }
}

$service = Registry::make(PostService::class);
```

---
[Back to Index](index.md) | [Registry Audit](registry-audit.md)
