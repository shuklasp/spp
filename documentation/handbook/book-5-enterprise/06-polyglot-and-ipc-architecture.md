# Book 5 Chapter 6 — Polyglot and IPC Architecture

## 1. Why leave one process?

An application may need a capability implemented in another language or another process.

The architectural boundary becomes:

```text
SPP application
   ↓
adapter / bridge
   ↓
IPC or external protocol
   ↓
other runtime
```

## 2. Keep business intent stable

The application service should express the business operation. A bridge maps that operation to the external runtime.

## 3. Failure boundaries

External execution introduces:

- timeout;
- serialization errors;
- protocol errors;
- unavailable process;
- version mismatch.

Treat them explicitly.

## 4. Lab

Create one Task Desk operation that invokes an external service through a stable adapter interface.

Then replace the external implementation without changing the application service contract.

## 5. Source-first rule

The repository may contain multiple bridge/runtime integrations. Document only the languages/protocols that the current implementation demonstrates.

## Checkpoint

> **Polyglot architecture separates business intent from the runtime or language used to implement one part of the operation.**
