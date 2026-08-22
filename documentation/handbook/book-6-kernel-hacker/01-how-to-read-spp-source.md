# Book 6 Chapter 1 — How to Read the SPP Source

## 1. Start at the public contract

Do not begin by opening the largest framework class.

Start with:

```text
application API
 ↓
configuration/manifest
 ↓
dispatcher/orchestrator
 ↓
tests/fixtures
 ↓
implementation
```

## 2. Search by concept

For a feature such as middleware, search for:

- public interface;
- kernel/dispatcher;
- commands;
- configuration keys;
- tests;
- documentation examples.

The repository's source and generated documentation can then be compared.

## 3. Hypothesis-driven reading

Before opening a class, write a hypothesis:

> “This class probably selects the middleware pipeline.”

Then verify it from call sites and tests.

## 4. Lab

Trace one Task Desk request through routing and middleware and produce a one-page source map.

## Checkpoint

> **Source reading is investigation: make a claim, find evidence, and update the claim when the code disagrees.**
