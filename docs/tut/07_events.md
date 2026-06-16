# 07. The Event System

SPP is designed around a powerful, cross-cutting **Event-Driven Hook** system. This allows you to extend the core framework's behavior without modifying its internal code.

---

## Core Concepts

| Term | Description |
|---|---|
| **Event** | A named point in the application lifecycle where custom logic can be hooked. |
| **EventParams** | A mutable object that carries data through the entire event chain. |
| **Listener** | A callable (closure, method, function) registered against an event name. |
| **Inline Handler** | An optional default closure passed directly to `fireEvent()`. |
| **Instead Hook** | A listener that completely replaces the inline handler (override). |
| **Priority** | An integer (0–1000). Higher values run first. Default is 500. |

---

## EventParams

`EventParams` is the standard payload container for all events. It replaces the legacy `&$params` array-by-reference pattern.

```php
// Create
$params = new \SPP\EventParams(['user' => $user, 'action' => 'login']);

// Read
$user = $params->get('user');

// Write
$params->set('status', 'approved');

// Bulk read/write
$all = $params->getPayload();   // returns the full array
$params->setPayload($all);      // replaces the full array

// Stop the event chain
$params->stopPropagation();
```

> **Key rule:** Every `fireEvent()` call requires an `EventParams` instance. Raw arrays are no longer accepted.

---

## Firing Events

### `fireEvent()` — Full Lifecycle (Overridable)

This is the primary method for dispatching events. It executes a three-phase lifecycle:

```
before_{event} → inline handler / instead hook → after_{event}
```

**Signature:**

```php
\SPP\SPPEvent::fireEvent(
    string   $event_name,
    \SPP\EventParams $params,
    ?callable $inline_handler = null   // optional default behavior
);
```

**Example — Simple event with no default behavior:**

```php
$params = new \SPP\EventParams(['page' => $currentPage]);
\SPP\SPPEvent::fireEvent('page_rendered', $params);
```

**Example — Event with inline default behavior:**

```php
$params = new \SPP\EventParams(['template' => 'home.blade.php']);

\SPP\SPPEvent::fireEvent('render_template', $params, function ($params) {
    // This is the DEFAULT behavior.
    // It runs only if no "instead" hook has been registered.
    $tpl = $params->get('template');
    include SPP_APP_DIR . '/views/' . $tpl;
});
```

The inline handler acts as a safe default. If a module later registers an `instead` hook for `render_template`, the inline handler is skipped entirely, and the module's hook runs in its place.

### `startEvent()` / `endEvent()` — Non-Overridable Events

For events where you want `before` and `after` hooks but **no possibility of override**, use these methods directly:

```php
$params = new \SPP\EventParams(['request' => $request]);

// Fire only before_* listeners
\SPP\SPPEvent::startEvent('http_request', $params);

// ... your core logic that cannot be overridden ...

// Fire only after_* listeners
\SPP\SPPEvent::endEvent('http_request', $params);
```

No `instead` hook can intercept this flow because the main event name is never dispatched.

---

## Listening to Events

### Inline Listener Registration

```php
// Listen to the main event
\SPP\SPPEvent::listen('page_rendered', function (\SPP\EventParams $params) {
    $page = $params->get('page');
    // do something
});

// Listen to before/after phases
\SPP\SPPEvent::listen('before_page_rendered', function ($params) {
    // runs before the main handler
});

\SPP\SPPEvent::listen('after_page_rendered', function ($params) {
    // runs after the main handler
});
```

### Overriding with an Instead Hook

```php
// This replaces the inline handler of 'render_template'
\SPP\SPPEvent::listen('instead_render_template', function ($params) {
    // Custom rendering logic
    $tpl = $params->get('template');
    echo MyCustomEngine::render($tpl);
});
```

When an `instead_` listener is registered, the inline handler passed to `fireEvent()` is **completely skipped**.

### Priority

Higher priority listeners run first:

```php
\SPP\SPPEvent::listen('page_rendered', $callbackA, false, 900); // runs first
\SPP\SPPEvent::listen('page_rendered', $callbackB, false, 100); // runs last
```

### Stopping Propagation

Any listener can halt the entire chain:

```php
\SPP\SPPEvent::listen('before_page_rendered', function ($params) {
    if ($params->get('blocked')) {
        $params->stopPropagation(); // skips main + after handlers
    }
});
```

---

## Class-Based Event Handlers

For complex modules, extend `\SPP\EventHandler` and use `addHook()` to register methods:

```php
namespace MyModule\Events;

class UserEventHandler extends \SPP\EventHandler
{
    protected function initHandler()
    {
        $this->addHook('before', 'onBeforeLogin', 800);
        $this->addHook('after',  'onAfterLogin');
    }

    public function onBeforeLogin(\SPP\EventParams $params)
    {
        // validate credentials, set flags, etc.
    }

    public function onAfterLogin(\SPP\EventParams $params)
    {
        // log the login, update session, etc.
    }
}
```

### `addHook()` Stages

| Stage | Hook Name Generated | Purpose |
|---|---|---|
| `before` | `before_{event_name}` | Runs before the main handler |
| `after` | `after_{event_name}` | Runs after the main handler |
| `instead` / `override` | `instead_{event_name}` | Completely replaces the inline handler |
| `main` | `{event_name}` | Adds a listener to the main event |

### Registering a Class Handler

```php
// Instantiating the handler auto-registers its hooks via initHandler()
\SPP\SPPEvent::registerHandler('user_login', '\\MyModule\\Events\\UserEventHandler');
```

---

## Declarative Registration via `events.yml`

Instead of registering handlers in PHP code, you can declare them in an `events.yml` file located at `etc/events.yml` within your app or module directory.

```yaml
events:
  user_login:
    - class: \MyModule\Events\UserEventHandler
      priority: 700

  page_rendered:
    - class: \MyModule\Events\PageHookHandler
```

SPP automatically scans these files during `SPPEvent::boot()`:

1. `spp/etc/events.yml` (core)
2. `src/{context}/etc/events.yml` (active app)
3. `spp/modules/{vendor}/{module}/etc/events.yml` (each module)

The compiled result is cached to `var/cache/events_compiled.php`. Clear this file after adding new handlers.

---

## Event Lifecycle Summary

```
fireEvent('my_event', $params, $inlineHandler)
│
├─ 1. before_my_event listeners  (sorted by priority, high → low)
│      └─ if stopPropagation() → STOP
│
├─ 2. Main Phase:
│      ├─ if instead_my_event listeners exist → run them (skip inline handler)
│      └─ else → run $inlineHandler, then my_event listeners
│      └─ if stopPropagation() → STOP
│
└─ 3. after_my_event listeners  (sorted by priority, high → low)
```

---

## Real-World Examples

### 1. Adding Global CSS

```php
\SPP\SPPEvent::listen('event_spp_include_css_files', function ($params) {
    \SPPMod\SPPView\ViewPage::addCssIncludeFile('res/custom_theme.css');
});
```

### 2. Intercepting Entity Save

```php
\SPP\SPPEvent::listen('before_entity:before_save', function (\SPP\EventParams $params) {
    $entity = $params->get('entity');
    // enforce business rules before any entity is saved
});
```

### 3. Overriding Template Rendering

```php
// In your module's init or events.yml handler:
\SPP\SPPEvent::listen('instead_render_template', function ($params) {
    $tpl = $params->get('template');
    echo MyThemeEngine::compile($tpl);
});
```

### 4. Non-Overridable Security Check

```php
$params = new \SPP\EventParams(['request' => $request]);
\SPP\SPPEvent::startEvent('security_check', $params);

// Core security logic that MUST NOT be overridden
$this->enforceCSRF($request);

\SPP\SPPEvent::endEvent('security_check', $params);
```

---

## Migration from Legacy API

| Legacy | Modern |
|---|---|
| `SPPEvent::registerEvent('evt')` | `SPPEvent::defineEvent('evt')` |
| `SPPEvent::registerHandler('evt', 'Handler')` | `SPPEvent::listen('evt', $callable)` |
| `SPPEvent::fireEvent('evt', &$array)` | `SPPEvent::fireEvent('evt', new EventParams($array))` |
| `SPPEvent::scanHandlers()` | `SPPEvent::boot()` (automatic) |
| `SPP\Core\EventManager::trigger()` | `SPPEvent::fireEvent()` |

Legacy methods like `registerHandler()`, `registerEvent()`, and `scanHandlers()` still exist as thin wrappers for backward compatibility but will be removed in a future release.

---

[**Next: Advanced Features**](08_advanced.md)
