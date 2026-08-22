# Book 6 Chapter 7 — Routing Discovery and Compilation

Trace one route from declaration to dispatch:

```text
pages.yml / attribute / generated artifact
→ discovery
→ route representation
→ compilation/cache where implemented
→ matching
→ handler dispatch
```

## Lab

Compare one page-oriented route and one attribute route and identify which runtime path they share and where they diverge.

## Rule

Do not assume the declaration format is the runtime representation. Frameworks commonly compile metadata into another structure.