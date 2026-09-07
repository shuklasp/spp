---
title: The SPP Request Lifecycle
duration: 15 mins
summary: Discover how incoming HTTP requests flow from .htaccess through the router to ViewControllers.
---

# The SPP Request Lifecycle

Welcome to the **Mastering SPP Framework** academy! In this first lesson, we will unpack how SPP delivers sub-millisecond request execution.

## 1. Single Entry Point Architecture

Every incoming request enters through `public/index.php`. The kernel initializes only the necessary bootstrap providers before evaluating routes.

```php
// Request boot sequence
$app = \SPP\App::getInstance();
$app->boot();
$app->handleRequest();
```

## 2. Zero-Cost Route Dispatching

Unlike heavyweight frameworks that inspect hundreds of classes on every boot, SPP compiles page routes in `etc/apps/{AppName}/pages.yml`.

### Key Benefits:
- **Zero Inline HTML**: Controllers serve clean external templates (`.blade.php`).
- **O(1) Route Resolution**: Exact-match hash tables for static endpoints.
- **Strict Content Negotiation**: Native support for HTMX partials and Turbo Streams.

> [!TIP]
> Always verify that your controller extends `\SPPMod\SPPView\ViewController` to inherit smart HTMX content negotiation and partial rendering helpers.