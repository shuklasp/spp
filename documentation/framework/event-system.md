# Core Component: Event System

The SPP Event System (`\SPP\SPPEvent`) is a sophisticated, stage-based messaging bus that enables the framework's decoupled architecture.

## 1. The 4-Stage Execution Pipeline
Every event fired via `fireEvent` follows a strict sequence:

### STAGE 1: BEFORE
*   **Purpose**: Parameter modification or validation.
*   **Execution**: All registered handlers are called in order of priority.
*   **Stop Propagation**: A handler can call `stopPropagation()` to prevent subsequent handlers and stages.

### STAGE 2: OVERRIDE
*   **Purpose**: To replace the core framework functionality.
*   **Trigger**: Triggered if a handler implements the `overrideHandler()` method.
*   **Effect**: If executed, the **Default Stage** is completely skipped.

### STAGE 3: DEFAULT
*   **Purpose**: The framework's internal implementation of the feature.
*   **Execution**: Only runs if STAGE 2 was not triggered.
*   **Note**: The core framework registers its logic as a "Default Handler".

### STAGE 4: AFTER
*   **Purpose**: Result augmentation or cleanup.
*   **Execution**: Always runs after the action (Stage 2 or 3) is complete.

## 2. Advanced Features
*   **Priority Ranking**: Handlers can define a priority (integer). Higher numbers run first.
*   **Subscribers**: Modules can use `getSubscribedEvents()` to register multiple handlers in a single class.
*   **Traceability**: When `SPP_DEBUG` is enabled, the system logs every event and its handlers to `var/logs/event_trace.json`.

## 3. Implementation Snippet
```php
\SPP\SPPEvent::fireEvent('event_name', $params, 'DefaultFallbackHandler');
```

---
[Back to Index](index.md)
