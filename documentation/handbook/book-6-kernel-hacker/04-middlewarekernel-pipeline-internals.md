# Book 6 Chapter 4 — MiddlewareKernel and Pipeline Internals

Trace the request boundary into the middleware runtime:

```text
request
→ MiddlewareKernel
→ pipeline construction
→ middleware invocation
→ next handler
```

## Lab

Choose one middleware and identify:

- how it is declared;
- how it becomes discoverable;
- where the kernel places it;
- how short-circuiting works.

## Rule

Use tests to establish ordering guarantees rather than assuming order from filenames or registration order.
