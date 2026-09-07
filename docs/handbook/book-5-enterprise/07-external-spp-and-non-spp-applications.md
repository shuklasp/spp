# Book 5 Chapter 7 — External SPP and Non-SPP Applications

## 1. Integration does not require rewriting

A legacy system can remain in place while an SPP application gradually takes over capabilities.

```mermaid
flowchart LR
    A[User] --> B[SPP application]
    B --> C[Adapter/API]
    C --> D[Legacy or external application]
```

## 2. Good boundary

Expose a stable application-facing contract and keep integration-specific translation inside an adapter.

## 3. Lab

Wrap a legacy student-information operation behind an SPP service interface. Add logging and explicit failure mapping.

## 4. Migration rule

Do not reproduce the old framework's architecture inside SPP merely to reduce migration effort. Translate responsibilities into native SPP concepts where practical.

## Checkpoint

> **External integration is a boundary, not an invitation to spread legacy assumptions through the SPP application.**
