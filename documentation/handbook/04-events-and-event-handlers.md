# Volume III — Runtime Infrastructure

## Chapter 4 — Events, EventHandler, and SPPEvent

**Evidence:** `spp/core/class.sppevent.php`, `spp/core/class.eventhandler.php`, `spp/core/class.eventparams.php`, `spp/core/attributes/On.php`, `spp/etc/*/events.yml`, event tests.

SPP has a modern event router (`SPP\SPPEvent`) together with a compatibility-oriented `SPP\EventHandler` abstraction. The implementation is more structured than a simple `EventEmitter`: it supports definitions, explicit listeners, attribute discovery, priorities, overridable events, and three-stage event execution.

## 4.1 Event definitions and listeners

`SPPEvent` maintains two main registries:

- `$eventDefinitions` — definition metadata such as default handlers and whether an event is overridable;
- `$listeners` — callbacks grouped by event/hook name.

Listeners carry a priority. The listener arrays are sorted descending, so a higher numeric priority executes first.

## 4.2 Event boot

`SPPEvent::boot()` loads a compiled cache when one exists. Otherwise it reads known `events.yml` files from framework/app/module locations, scans eligible PHP files for `#[On]` attributes, and writes a compiled listener/definition cache.

The source intentionally avoids a blanket class-file scan for YAML registration; attribute discovery is a separate scan of application and module PHP trees.

```text
SPPEvent::boot()
      │
      ├── compiled cache exists? ── yes ──► load listeners/definitions
      │
      └── no
           ├── parse known events.yml files
           ├── parse active application/module event definitions
           ├── scan #[On] attributes
           └── write compiled event cache
```

## 4.3 Attribute-based listeners

`SPP\Attributes\On` is a repeatable method attribute. During attribute scanning, SPP reflects eligible classes, skips classes implementing `FrontendComponentInterface`, and registers every discovered `[On]` method with its event name and priority.

This provides a declarative listener style:

```php
#[\SPP\Attributes\On('StudentCreated', priority: 700)]
public function rebuildIndex(EventParams $params): void
{
    // ...
}
```

The exact parameter order and constructor semantics should be taken from `spp/core/attributes/On.php` in the version being documented.

## 4.4 EventHandler compatibility layer

`SPP\EventHandler` is an abstract class that preserves an older handler API while routing modern registration through `SPPEvent::listen()`.

It exposes:

- `event_name` and `handler_name` state;
- a default priority of 500;
- propagation control;
- `getSubscribedEvents()` for explicit subscription declarations;
- `addHook()` plus compatibility wrappers `addBeforeHandler()`, `addAfterHandler()`, and `addOverrideHandler()`.

The legacy `beforeHandler()`, `overrideHandler()`, and `afterHandler()` methods are intentionally no-ops in the modern router. This is important when maintaining older handlers: the modern router is the execution path.

## 4.5 Event execution pipeline

`SPPEvent::fireEvent()` implements a concrete multi-stage flow.

```text
fireEvent(event, params)
        │
        ▼
 before_<event>
        │
        ├── stopped? ─────────────► after_<event>
        │
        ▼
 override_<event> exists?
        │
      yes ──► execute override
        │
       no
        │
        ├── execute inline handler, if supplied
        ├── execute configured default handler, if defined
        └── execute listeners registered on <event>
        │
        ▼
 propagation stopped?
        │
        ▼
 after_<event>
```

The implementation stops listener execution when `EventParams::isPropagationStopped()` becomes true.

## 4.6 Overridable events

`defineEvent()` stores whether an event is overridable and, optionally, a default handler. When `listen()` is called with `$isOverride = true`, SPP checks the event definition before replacing the event's current listener collection.

An override is therefore not merely another high-priority listener: it is a replacement path, and it is rejected for events that are not declared overridable.

## 4.7 Inline handlers and default handlers

`fireEvent()` can receive an inline callback. The runtime can resolve string class names and array callbacks by reflection, instantiating non-static methods where necessary. Separately, an event definition can nominate a default handler.

This means an event can have three conceptually different mechanisms:

1. an inline handler supplied by the caller;
2. a configured default handler; and
3. registered listeners.

## 4.8 EventParams and mutation

The event pipeline passes an `EventParams` object by reference to callbacks. The object carries a payload and a propagation-stopped flag. Because handlers can modify the payload, SPP events are not necessarily immutable notifications; they can also implement **ordered data transformation and interception**.

This is why the handbook distinguishes SPP events from simpler publish-only event emitters.

## 4.9 Event tracing

SPPEvent contains trace-related state and debug logging. In debug mode, `fireEvent()` writes event names to the configured SPP log directory.

Additional trace methods exist (`getCollectedTrace()`, `clearTrace()`, `persistTrace()`), but the current source should be treated as the authority for which are operational and which are compatibility placeholders.

## 4.10 Comparison

| Concern | Generic EventEmitter | SPPEvent |
|---|---|---|
| Listener priority | Often custom | Built in |
| Before/after stages | Usually custom | Built in |
| Overridable event definition | Usually absent | Built in |
| Attribute listener discovery | Usually absent | `#[On]` |
| Mutable event payload | Depends | `EventParams` supports it |
| Propagation stop | Common | Built in |
| Compiled listener cache | Uncommon | Implemented |

## 4.11 Kernel Hacker note

SPP's event model is best understood as a **hook-capable dispatch pipeline** rather than a pure pub/sub bus. The distinction becomes especially important when integrating routing, context detection, default handlers, and overridable framework behavior.
