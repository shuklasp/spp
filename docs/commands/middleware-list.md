# NAME
`middleware:list` - List the middleware pipeline for an app

# SYNOPSIS
`php spp.php middleware:list [--app=APP_NAME]`

# PURPOSE
Introspects and displays the ordered HTTP middleware pipeline registered for a specific application context. It provides visibility into the chain of middleware classes that process incoming HTTP requests and outgoing responses.

# OPTIONS AVAILABLE
- `--app=APP_NAME` : Specifies the application context to query. Defaults to `default` if omitted.

# UNDER THE HOOD ACTIVITY
The command isolates its execution by bootstrapping the specific application environment using `\SPP\Scheduler::withContext()`. Inside this isolated closure, it forces the initialization of the application's middleware stack by invoking `SPP\Core\MiddlewareKernel::boot()`.
Because the middleware pipeline is securely encapsulated as a protected static property within the `MiddlewareKernel` class, the command employs PHP's `ReflectionClass` and `ReflectionProperty` to forcefully bypass visibility restrictions (`setAccessible(true)`). It extracts the raw array of registered middleware class names.
Finally, it iterates over this array, tracking the sequence index, and formats the pipeline into a human-readable tabular output on the terminal, clearly illustrating the exact execution order of the middleware layers.

# EXAMPLES
- `php spp.php middleware:list` - Lists the middleware pipeline for the default app.
- `php spp.php middleware:list --app=admin` - Lists the middleware pipeline specifically for the `admin` app context.
