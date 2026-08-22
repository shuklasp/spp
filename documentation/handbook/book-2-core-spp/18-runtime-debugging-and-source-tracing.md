# Book 2 Chapter 18 — Runtime Debugging and Source Tracing

## The debugging ladder

When an SPP feature fails, do not jump randomly through source files. Move downward by evidence:

```text
symptom
  ↓
request/command boundary
  ↓
application context
  ↓
configuration
  ↓
module/registry activation
  ↓
middleware/routing/event dispatch
  ↓
application service
  ↓
data/transport/external integration
  ↓
implementation
```

## Use the smallest useful question

Ask:

> What is the earliest layer at which the observed behavior becomes wrong?

This prevents debugging a controller when routing failed or debugging a database query when the wrong application context was selected.

## Source-reading workflow

1. Find the public API used by the application.
2. Find the configuration/manifest that activates it.
3. Find the dispatcher/orchestrator.
4. Find tests and fixtures.
5. Only then inspect lower-level implementation.

## Hands-on lab

Break one Task Desk feature at each boundary:

- configuration;
- route;
- middleware;
- event;
- service binding;
- persistence.

Record the first observable symptom and the earliest useful source location.

## Checkpoint

> **Framework debugging is boundary diagnosis, not random source-file inspection.**
