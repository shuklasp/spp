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
Every middleware must implement the `\SPP\Core\MiddlewareInterface`.

```php
namespace SPP\App\Middleware;
use \SPP\Core\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface {
    public function handle($request, callable $next) {
        // 1. Logic BEFORE the app runs
        if (!\SPP\Auth::check()) {
            return redirect('/login');
        }

        // 2. Pass to the NEXT layer
        $response = $next($request);

        // 3. Logic AFTER the app runs
        return $response;
    }
}
```

---

## 3. Configuration
Middlewares can be registered globally or per application.

### Global Registration (`spp/etc/middleware.yml`)
```yaml
global:
  - \SPP\App\Middleware\CorsMiddleware
  - \SPP\App\Middleware\SecurityHeadersMiddleware
```

---

## 4. Performance
The pipeline uses a highly optimized `array_reduce` implementation, ensuring that even deep middleware stacks have negligible overhead on request performance.

---
[Back to Index](index.md)
