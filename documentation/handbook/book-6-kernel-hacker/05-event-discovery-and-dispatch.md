# Book 6 Chapter 5 — Event Discovery and Dispatch Internals

Trace an event from declaration to handler:

```text
SPPEvent definition
→ listener discovery
→ EventHandler registration
→ parameter construction
→ priority/stage dispatch
→ propagation state
```

## Lab

Trace one TaskCreated event and produce a source map for its handlers.

## Rule

Do not infer event ordering, propagation, or override semantics from names alone; verify the dispatcher and tests.
