# Book 6 Chapter 2 — Runtime Bootstrap and Scheduler Tracing

Trace one execution path:

```text
entry point
→ bootstrap
→ Scheduler
→ application context
→ initialization
→ runtime
```

Start from the public scheduler/context API and then follow calls inward.

## Lab

Map one web execution and one CLI execution and identify which lifecycle stages differ.

## Rule

Do not infer multi-process or distributed semantics from Scheduler naming alone.
