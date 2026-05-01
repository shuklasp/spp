# Core Component: Scheduler

The `\SPP\Scheduler` is the primary traffic controller of the SPP framework. It is responsible for mapping incoming HTTP requests to specific application contexts.

## 1. Context Detection Logic
The `detectAndEnforceContext()` method follows a multi-tiered discovery process:

1.  **Dynamic Scanning**: It scans the `src/` directory for any folder containing an `etc/app.yml` file.
2.  **Context Enforcement Event**: It fires `event_spp_context_enforce`. This event allows modules to force a context before any matching logic runs.
3.  **Longest Prefix Match**: It compares the `REQUEST_URI` against the `base_url` of all discovered apps. The app with the longest matching prefix wins.
4.  **Route Resolve Event**: It fires `event_spp_route_resolve` to allow final adjustments after a match is found.

## 2. Key Methods
*   `setContext(string $context)`: Switches the active execution context and updates the internal process registry.
*   `getActiveProc()`: Returns the `\SPP\App` instance currently in control.
*   `regProc(\SPP\App $proc)`: Registers a new application process into the scheduler's pool.

## 3. Overridability
The routing logic is not hardcoded. It is implemented as a default handler for the `event_spp_context_enforce` event.

**How to override routing:**
```php
class MyRouter extends \SPP\EventHandler {
    public function overrideHandler(&$params) {
        // Implement custom domain-based or header-based routing here
        $params['context'] = 'my_custom_app';
    }
}
```

---
[Back to Index](index.md)
