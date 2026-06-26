# NAME
`make:middleware` - Create a new middleware class

# SYNOPSIS
`php spp.php make:middleware <name> [--app=appname]`

# PURPOSE
The `make:middleware` command rapidly scaffolds an HTTP Middleware class. Middleware acts as a filtering layer intercepting requests before they hit controllers or intercepting responses before they are returned to the client, commonly utilized for authentication, CORS, or logging.

# OPTIONS AVAILABLE
- `<name>` (string, required): The logical name of the middleware (e.g. `RequireAuth`, `RateLimiter`).
- `--app=<appname>` (string, optional): Determines the target execution context namespace.

# UNDER THE HOOD ACTIVITY
It normalizes the provided name and isolates the application context via `getContext()`. It calculates the fully qualified `Middleware` namespace and targets the `middleware` subfolder, ensuring the PHP file is prefixed as `class.{lowercase_middleware_name}.php`.
The command utilizes the internal `buildFromStub()` generator against the `middleware` stub configuration, dynamically populating the namespace and class structure. It advises the user post-generation to explicitly register the new middleware within the `spp/etc/middleware.yml` mapping file or app-specific configurations.

# EXAMPLES
**1. Scaffold a rate limiting middleware:**
```bash
php spp.php make:middleware ThrottleRequests --app=api
```
