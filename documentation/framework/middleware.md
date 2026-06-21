# Core Component: Middleware Pipeline

The SPP Middleware Pipeline provides a structured, onion-style execution model for request and response processing. It allows developers to inject protective or transformative logic before and after the core application logic.

## 1. The Onion Pattern
Middlewares wrap the request in layers. A request flows **IN** through the layers and a response flows **OUT** through the same layers.

```
[ Request ] -> [ Layer 1 ] -> [ Layer 2 ] -> [ App Logic ]
                                                    |
[ Response ] <- [ Layer 1 ] <- [ Layer 2 ] <--------'
```

---

## 2. Technical Contract

Every middleware must implement `\SPP\Core\MiddlewareInterface`:

```php
namespace SPP\Core;

interface MiddlewareInterface
{
    public function handle($request, \Closure $next);
}
```

The `$next` closure represents the next middleware layer. A middleware can:
- **Pass through** by calling `return $next($request)`
- **Short-circuit** by returning a response or calling `exit` (e.g., 401, 429)
- **Post-process** by capturing `$next($request)` return value and modifying it

### Example: Authentication Middleware

```php
namespace App\Middleware;

use SPP\Core\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle($request, \Closure $next)
    {
        if (!\SPPMod\SPPAuth\SPPAuth::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $response = $next($request);

        return $response;
    }
}
```

### Example: Post-Processing (Security Headers)

```php
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
```

---

## 3. The Pipeline Engine

`\SPP\Core\Pipeline` uses `array_reduce` on a reversed middleware array to build a nested closure stack:

```php
(new Pipeline())
    ->send($_REQUEST)
    ->through($middlewareList)
    ->then($destination);
```

Middleware resolution inside the pipeline:
1. **String class names** → instantiated via `\SPP\Registry::make($pipe)` (DI-aware)
2. **MiddlewareInterface objects** → `->handle($passable, $stack)` called
3. **Plain callables** → invoked directly as `$pipe($passable, $stack)`

---

## 4. The Middleware Kernel

`\SPP\Core\MiddlewareKernel` orchestrates the global middleware stack. Its `boot()` assembles middleware from three sources:

| Priority | Source | Method |
|---|---|---|
| 1 | Hardcoded + Registry | `ApiAuthMiddleware` always first, plus `Registry::get('__middleware=>global')` |
| 2 | Global YAML | `spp/etc/middleware.yml` → `global:` key |
| 3 | App YAML | `<app>/etc/middleware.yml` → `global:` key (when context ≠ `default`) |

### Global Configuration (`spp/etc/middleware.yml`)

```yaml
global:
  - SPP\Core\Middleware\CSRFMiddleware
  - SPPMod\SPPLogger\RequestLogger
```

### App-Specific Configuration

```yaml
# src/<appname>/etc/middleware.yml
global:
  - App\MyApp\Middleware\TenantResolver
```

### Programmatic Registration

```php
\SPP\Core\MiddlewareKernel::addGlobalMiddleware(
    \App\Middleware\CustomMiddleware::class
);
```

---

## 5. Kernel Invocation

In `index.php`, the kernel wraps all routing:

```php
\SPP\Core\MiddlewareKernel::run(function($request) {
    // All routing and dispatching lives here
    \SPPMod\SPPView\ViewPage::showPage();
});
```

The `run()` method:
1. Calls `boot()` to assemble the middleware stack
2. Fires `event_spp_kernel_boot`
3. Sends `$_REQUEST` through all middleware via `Pipeline`
4. Calls the destination closure

---

## 6. Route-Level Middleware

SPP supports route-scoped middleware via PHP 8 Attributes.

### `#[Middleware]` Attribute

```php
use SPPMod\SPPView\Attributes\Route;
use SPPMod\SPPView\Attributes\Middleware;

#[Middleware(AuthMiddleware::class)]          // class-level
class AdminController
{
    #[Route('/admin/settings')]
    #[Middleware(CsrfMiddleware::class)]      // method-level
    public function settings() { ... }
}
```

### `#[Route]` Inline Middleware

```php
#[Route('/api/data', middleware: [RateLimiterMiddleware::class])]
public function getData() { ... }
```

### Merge Order

```
class-level #[Middleware] + method-level #[Middleware] + #[Route] middleware param
```

---

## 7. Built-In Middleware

| Middleware | Scope | Purpose |
|---|---|---|
| `ApiAuthMiddleware` | Global (hardcoded) | JWT/API key auth for `api/v1/*` |
| `CSRFMiddleware` | Global (YAML) | CSRF token validation |
| `RequestLogger` | Global (YAML) | Request logging |
| `RateLimiterMiddleware` | Route/Manual | IP-based rate limiting via Cache |
| `ThrottleMiddleware` | Route-level | Token-bucket rate limiting |
| `SecurityHeadersMiddleware` | Route-level | Security response headers |
| `CsrfMiddleware` (sppsecurity) | Route-level | Config-aware CSRF for state-changing methods |

---

## 8. CLI Commands

```bash
# Scaffold a new middleware
php spp/spp.php make:middleware MyMiddleware

# List all registered middleware
php spp/spp.php middleware:list
```

---

## 9. Request Flow

```
HTTP Request
  → MiddlewareKernel::run()
    → ApiAuthMiddleware → CSRFMiddleware → RequestLogger
      → destination (app routing)
        → ViewRouter / SPPAPI / AutoApiRouter
          → route-level middleware (#[Middleware] / #[Route])
            → Controller method
          ← response
        ← ...
      ← post-processing layers
    ← response
  → Browser
```

---

## 10. Performance

The pipeline uses a highly optimized `array_reduce` implementation, ensuring that even deep middleware stacks have negligible overhead on request performance.

---
[Back to Index](index.md)
