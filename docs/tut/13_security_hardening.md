# 13. Security Hardening (The Brutal Audit)

Welcome to the **Security Hardening** chapter. Security is the paramount feature of the SPP Framework. In order to construct an enterprise-grade portal, your core foundation must be cryptographically sound and mathematically secure against all vectors of attack.

The SPP Framework has recently undergone an exhaustive, framework-wide security audit known as the **"Brutal Audit"**. This chapter covers the major defenses implemented natively across the architecture.

---

## 1. SQL Injection (SQLi) Elimination
Legacy database interactions are highly vulnerable to SQL injection, especially in dynamic `ORDER BY` or `WHERE` clauses. 
- **QueryBuilder Sanitization:** Both `sppdb` and `sppxdb` rigidly enforce regex column sanitization (`[^a-zA-Z0-9_\.]`). Operators are actively whitelisted against a hard-coded array (`=`, `!=`, `<`, `>`, `LIKE`, `IN`, `IS NULL`, etc.). It is physically impossible to execute chained commands or subqueries through the QueryBuilder.
- **Reporting Engine Integrity:** `sppreport` completely blocks spaces and quotes in dynamic `CUSTOM` aggregate fields. This means dynamic reporting calculations are limited solely to math (`SUM(amount * tax_rate)`), completely rejecting any embedded subqueries (e.g., `(SELECT password FROM users LIMIT 1)`).

## 2. Path Traversal & LFI Mitigations
When frameworks dynamically resolve file paths based on URLs or template directives, they open themselves to Local File Inclusion (LFI).
- **View Router (`spprouter`):** Deep directory traversal tokens (`..`) are stripped, and absolute paths are validated.
- **PHP Components (`<php-include>`):** `sppview` recursively checks included files and blocks path traversal characters natively at the AST compilation layer.
- **File Disk Wrappers (`sppstorage`):** `LocalDisk` aggressively filters path traversal payloads before executing any `fopen()` or `file_get_contents()` calls.

## 3. RCE & PHP Object Injection (POP) Blocking
Deserializing arbitrary data is incredibly dangerous and historically leads to Remote Code Execution via POP chains.
- **Safe Caching:** `sppcache` executes `unserialize()` with the strict `['allowed_classes' => false]` directive. If a malicious payload attempts to inject an object, PHP safely neuters it into `__PHP_Incomplete_Class`.
- **Inheritance-Bound Queues:** `sppqueue` job deserialization rigorously enforces `is_subclass_of($job, \SPP\Job::class)`. Rogue classes are dropped from memory instantly before any magic methods (`__destruct()`) can fire.
- **Code Stub Generation:** `sppmaker` securely regex-whitelists entity names (`\w+`) to prevent evaluating arbitrary code within the generation templates.

## 4. Log Forging (Injection)
When logging user behavior, inserting unfiltered payloads like HTTP User Agents directly into a text log file can allow attackers to inject carriage returns (`\r\n`) and write falsified log lines.
- **CRLF Stripping:** `spplogger` actively sanitizes user-supplied HTTP headers and messages using `str_replace()`, condensing multiline payloads and preventing log manipulation.

## 5. WebSocket Integrity
WebSockets typically suffer from cross-site websocket hijacking (CSWSH) and unauthenticated broadcast spoofing.
- **HMAC Signatures:** The `spplive` Engine utilizes HMAC SHA-256 digital signatures (`X-SPP-Live-Signature`) on all internal broadcast API requests. 

---

## 6. Middleware-Based Security Layer

SPP enforces critical security policies through its **Middleware Pipeline** — a layered onion architecture that intercepts every HTTP request before routing occurs. Security middleware runs automatically on every request via the `MiddlewareKernel`.

### CSRF Protection

SPP provides two complementary CSRF middleware implementations:

**Global CSRF Middleware** (`SPP\Core\Middleware\CSRFMiddleware`) runs on every request via `middleware.yml`. It validates tokens on all `api.php` and `sppux_api` endpoints (except `login` and `check_auth`), checking both the `csrf_token` request parameter and the `X-CSRF-TOKEN` header:

```php
// Token is validated against the session
$submittedToken = $_REQUEST['csrf_token'] ?? '';
if (!$submittedToken) {
    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
}
```

**Route-level CSRF Middleware** (`SPPMod\SPPSecurity\Middleware\CsrfMiddleware`) can be applied to specific controllers via the `#[Middleware]` attribute. It validates only state-changing methods (POST, PUT, DELETE, PATCH) and respects per-app configuration:

```php
#[Middleware(\SPPMod\SPPSecurity\Middleware\CsrfMiddleware::class)]
class AdminController {
    // CSRF enforced on all POST/PUT/DELETE/PATCH requests
}
```

### API Authentication

`ApiAuthMiddleware` is **hardcoded** as the first middleware in the global pipeline. It intercepts all `api/v1/*` routes and enforces authentication via:
- **Bearer tokens** (JWT) from the `Authorization` header
- **API keys** from the `api_key` query parameter

Token verification is delegated to the event system (`api.auth.verify_token`), allowing modules to plug in custom validation logic.

### Rate Limiting & Throttling

Two middleware implementations handle rate limiting:

- **`RateLimiterMiddleware`** — IP-based rate limiting using `SPP\Cache`. Returns `429 Too Many Requests` with `Retry-After` and `X-RateLimit-*` response headers.
- **`ThrottleMiddleware`** — Token-bucket algorithm via `SPPSecurityService`. Configurable max requests and decay window.

```php
// Apply throttling to specific routes
#[Route('/api/heavy-endpoint')]
#[Middleware(\SPPMod\SPPSecurity\Middleware\ThrottleMiddleware::class)]
public function heavyEndpoint() { ... }
```

### Security Headers

`SecurityHeadersMiddleware` adds defensive HTTP headers on the **response** path (post-processing):

```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### Configuration

Global security middleware is declared in `spp/etc/middleware.yml`:

```yaml
global:
  - SPP\Core\Middleware\CSRFMiddleware
  - SPPMod\SPPLogger\RequestLogger
```

Apps can layer additional security middleware in their own `etc/middleware.yml`.

> For a complete guide to the middleware architecture, see [Chapter 15: The Middleware Pipeline](15_middleware.md).

---

By utilizing the standard framework primitives (`SPPDB`, `SPPLogger`, `SPPCache`, `SPPQueue`, `LiveComponent`) and the middleware pipeline, your application automatically inherits this impenetrable defense matrix.

---

[**Previous: Live Components**](12_live_components.md) | [**Next: Blogging Platform**](14_blogging_platform.md)
