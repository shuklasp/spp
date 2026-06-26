# 15. The Middleware Pipeline

SPP implements a layered **onion/pipeline** middleware architecture that intercepts every HTTP request before it reaches your application logic. Middleware can authenticate, rate-limit, log, add headers, validate CSRF tokens, or short-circuit requests entirely.

---

## Core Concepts

| Term | Description |
|---|---|
| **MiddlewareInterface** | The contract every middleware must implement (`handle($request, \Closure $next)`) |
| **Pipeline** | The engine that builds a nested closure stack and executes middleware in order |
| **MiddlewareKernel** | The orchestrator that collects global middleware from config, registry, and apps |
| **Global Middleware** | Runs on every HTTP request before routing occurs |
| **Route Middleware** | Attached to specific controllers or methods via PHP Attributes |

---

## The Contract: `MiddlewareInterface`

Every middleware class must implement `\SPP\Core\MiddlewareInterface`:

```php
namespace SPP\Core;

interface MiddlewareInterface
{
    /**
     * @param mixed    $request The request context
     * @param \Closure $next    The next middleware in the pipeline
     * @return mixed   The response
     */
    public function handle($request, \Closure $next);
}
```

The `$next` closure represents the next layer in the onion. A middleware can:

- **Pass through** — call `return $next($request)` to continue to the next layer
- **Short-circuit** — return a response or `exit` to block the request entirely
- **Post-process** — capture the return value of `$next($request)` and modify it before returning

---

## Writing a Middleware

### Basic Example: Authentication Guard

```php
namespace App\Middleware;

use SPP\Core\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle($request, \Closure $next)
    {
        // 1. Logic BEFORE the app runs
        if (!\SPPMod\SPPAuth\SPPAuth::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // 2. Pass to the NEXT layer
        $response = $next($request);

        // 3. Logic AFTER the app runs (post-processing)
        return $response;
    }
}
```

### Post-Processing Example: Security Headers

```php
namespace App\Middleware;

use SPP\Core\MiddlewareInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle($request, \Closure $next)
    {
        // Pass request through first
        $response = $next($request);

        // Add headers AFTER the app logic completes
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
```

### Short-Circuit Example: Rate Limiter

```php
namespace App\Middleware;

use SPP\Core\MiddlewareInterface;
use SPP\Cache;

class RateLimiterMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $decaySeconds;

    public function __construct(int $maxRequests = 100, int $decaySeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->decaySeconds = $decaySeconds;
    }

    public function handle($request, \Closure $next)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = "rate_limit:" . md5($ip);
        $hits = (int) Cache::get($key);

        if ($hits >= $this->maxRequests) {
            http_response_code(429);
            header('Retry-After: ' . $this->decaySeconds);
            echo json_encode(['error' => 'Too Many Requests']);
            exit;  // Short-circuit — $next() is never called
        }

        Cache::set($key, $hits + 1, $this->decaySeconds);
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . ($this->maxRequests - $hits - 1));

        return $next($request);
    }
}
```

---

## The Pipeline Engine

The `\SPP\Core\Pipeline` class uses `array_reduce` on a reversed array of middleware to build a nested closure stack (the classic "onion" pattern):

```php
(new Pipeline())
    ->send($_REQUEST)          // the "passable" (request data)
    ->through($middlewareList) // array of class names
    ->then($destination);      // the final handler (app logic)
```

Inside the pipeline, each middleware is resolved:

1. **String class names** are instantiated via `\SPP\Registry::make($pipe)` (DI-aware)
2. Objects implementing `MiddlewareInterface` have `->handle($passable, $stack)` called
3. Plain callables are invoked directly as `$pipe($passable, $stack)`

---

## The Middleware Kernel

`\SPP\Core\MiddlewareKernel` orchestrates the **global** middleware stack. Its `boot()` method assembles middleware from three sources (in priority order):

| Priority | Source | How |
|---|---|---|
| 1 | Hardcoded + Registry | `ApiAuthMiddleware` is always first. Additional middleware from `\SPP\Registry::get('__middleware=>global')` |
| 2 | Global YAML config | `spp/etc/middleware.yml` → `global:` key |
| 3 | App-specific config | `<app>/etc/middleware.yml` → `global:` key (loaded when context ≠ `default`) |

### Global YAML Configuration

Register global middleware in `spp/etc/middleware.yml`:

```yaml
# Global Middleware Stack
# These run on every request before any dispatching occurs.
global:
  - SPP\Core\Middleware\CSRFMiddleware
  - SPPMod\SPPLogger\RequestLogger
```

### App-Specific Middleware

Each application can define its own middleware stack in `src/<appname>/etc/middleware.yml`:

```yaml
global:
  - App\MyApp\Middleware\TenantResolver
  - App\MyApp\Middleware\LocaleDetector
```

### Programmatic Registration

Modules can inject middleware dynamically during boot:

```php
\SPP\Core\MiddlewareKernel::addGlobalMiddleware(
    \App\Middleware\CustomMiddleware::class
);
```

This method calls `boot()` first to prevent overwriting, then appends the middleware (with deduplication).

---

## How the Kernel is Invoked

In `index.php`, the kernel wraps **all** application routing and dispatching:

```php
\SPP\Core\MiddlewareKernel::run(function($request) {
    // ALL routing lives inside here as the "destination"
    // - SPPAPI handler
    // - AutoApiRouter (REST)
    // - ViewRouter (pages)
    // - SCIM / OAuth endpoints
    \SPPMod\SPPView\ViewPage::showPage();
});
```

The `run()` method:
1. Calls `boot()` to assemble the middleware stack
2. Fires the `event_spp_kernel_boot` event
3. Creates a `Pipeline`, sends `$_REQUEST` through all global middleware, then calls the destination closure

---

## Route-Level Middleware

In addition to global middleware, SPP supports **route-scoped** middleware via PHP 8 Attributes.

### The `#[Middleware]` Attribute

Apply at **class level** (affects all methods) or **method level**:

```php
namespace App\Controllers;

use SPPMod\SPPView\Attributes\Route;
use SPPMod\SPPView\Attributes\Middleware;

#[Middleware(\App\Middleware\AuthMiddleware::class)]
class AdminController
{
    #[Route('/admin/settings', method: 'GET')]
    #[Middleware(\SPPMod\SPPSecurity\Middleware\CsrfMiddleware::class)]
    public function settings()
    {
        // AuthMiddleware runs first (class-level)
        // CsrfMiddleware runs second (method-level)
        return 'Settings page';
    }

    #[Route('/admin/dashboard')]
    public function dashboard()
    {
        // Only AuthMiddleware runs (class-level)
        return 'Dashboard';
    }
}
```

### The `#[Route]` Middleware Parameter

The `#[Route]` attribute also accepts an inline `middleware` array:

```php
#[Route('/api/data', middleware: [RateLimiterMiddleware::class])]
public function getData()
{
    // ...
}
```

### How Middleware Merges

The `RouteScanner` collects middleware from all three sources and merges them in this order:

```
class-level #[Middleware] + method-level #[Middleware] + #[Route] middleware param
```

The `ViewRouter` then executes each middleware's `handle()` method before calling the controller.

---

## Generating Middleware with the CLI

SPP provides a CLI command to scaffold new middleware:

```bash
php spp/spp.php make:middleware MyCustomMiddleware
```

This generates a middleware class from a stub template with the correct interface implementation.

You can also list all registered middleware:

```bash
php spp/spp.php middleware:list
```

---

## Built-In Middleware Inventory

| Middleware | Scope | Purpose |
|---|---|---|
| `SPP\Core\Middleware\ApiAuthMiddleware` | Global (hardcoded) | JWT and API key authentication for `api/v1/*` routes |
| `SPP\Core\Middleware\CSRFMiddleware` | Global (YAML) | CSRF token validation on admin and API endpoints |
| `SPPMod\SPPLogger\RequestLogger` | Global (YAML) | Logs every incoming request |
| `SPP\Core\Middleware\RateLimiterMiddleware` | Route / Manual | IP-based rate limiting using `SPP\Cache` |
| `SPPMod\SPPSecurity\Middleware\ThrottleMiddleware` | Route-level | Token-bucket rate limiting via `SPPSecurityService` |
| `SPPMod\SPPSecurity\Middleware\SecurityHeadersMiddleware` | Route-level | Adds X-Frame-Options, HSTS, XSS-Protection headers |
| `SPPMod\SPPSecurity\Middleware\CsrfMiddleware` | Route-level | Config-aware CSRF validation for POST/PUT/DELETE/PATCH |

---

## Request Flow Summary

```
HTTP Request
  → index.php
    → MiddlewareKernel::run()
      → Pipeline builds onion stack from global middleware
        → ApiAuthMiddleware
          → CSRFMiddleware
            → RequestLogger
              → destination closure (app routing)
                → ViewRouter / SPPAPI / AutoApiRouter
                  → (route-level middleware from #[Middleware] / #[Route])
                    → Controller method
                  ← response flows back out through each layer
                ← ...
              ← RequestLogger (post-process)
            ← CSRFMiddleware (post-process)
          ← ApiAuthMiddleware (post-process)
        ← Pipeline returns
      ← Kernel returns
    ← Response sent to browser
```

---

## Best Practices

1. **Keep middleware focused** — each middleware should do one thing well
2. **Always call `$next($request)`** unless you intend to block the request
3. **Use global middleware sparingly** — it runs on every single request
4. **Prefer route-level middleware** for feature-specific concerns (auth, CSRF, throttling)
5. **Use the `middleware.yml` config** for middleware that applies to entire applications
6. **Use `#[Middleware]` attributes** for controller-specific middleware
7. **Short-circuit early** — check conditions and `exit` before calling `$next()` for best performance

---

[**Next: Back to Index**](index.md)
