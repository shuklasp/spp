# Book 6 Chapter 10 — SPPLive Engine Selection Internals

Trace the current SPPLive implementation:

```text
SPPLive entry point
→ available-engine detection
→ engine selection
→ transport initialization
→ client/server interaction
```

## Lab

Disable one supported engine in a controlled environment and trace the implementation's actual fallback/selection path.

## Rule

Do not assume an engine is selected merely because its class exists. Trace the selection logic.