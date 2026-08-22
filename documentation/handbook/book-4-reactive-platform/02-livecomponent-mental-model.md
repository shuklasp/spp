# Book 4 Chapter 2 — LiveComponent Mental Model

## 1. What LiveComponent is solving

A server-rendered component can become difficult to use interactively when every small action requires rebuilding an entire page.

A LiveComponent keeps a server-side component model that can respond to later client interactions.

Conceptually:

```text
initial request
   ↓
component state
   ↓
render
   ↓
client interaction
   ↓
later component request
   ↓
restore component state
   ↓
action / update
   ↓
render again
```

## 2. It is not just a template

A template describes presentation.

A LiveComponent combines:

- state;
- actions;
- lifecycle;
- validation where supported;
- rendering;
- state transfer between requests.

## 3. The current SPP implementation

The changed LiveComponent implementation contains explicit hydration/dehydration behavior, public-property serialization, lifecycle hooks, validation integration, and state-integrity checking.

That makes the lifecycle itself a central teaching topic.

## 4. Hands-on lab

Turn the Task Desk detail area into a server-side reactive component with one public property and one user action.

Document the component's state before and after the action.

## 5. Failure lab

Change the server-side component property between interactions and observe the actual integrity/restoration behavior.

## Checkpoint

> **LiveComponent is a server-side stateful interaction model whose lifecycle spans more than the initial page request.**
