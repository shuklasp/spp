# Book 2 Chapter 14 — Parikshak and Framework-Native Testing

## 1. Why framework testing is different

A plain unit test can instantiate a PHP class. A framework application may depend on:

- application context;
- configuration;
- container bindings;
- modules;
- routing;
- middleware;
- events;
- database;
- HTTP/API behavior.

A testing framework that understands those boundaries can test more realistic behavior.

## 2. Testing as part of learning

The handbook uses this loop for every major subsystem:

```text
Learn
 ↓
Build
 ↓
Test
 ↓
Break
 ↓
Diagnose
 ↓
Fix
 ↓
Regression test
```

## 3. Parikshak

SPP includes Parikshak as its framework-oriented testing branch. Exact command and assertion APIs must follow the current repository implementation/documentation.

The important architectural lesson is that tests should exercise the same application contracts the runtime uses.

## 4. Test levels

Separate:

```text
unit test
integration test
application test
HTTP/API test
system/feature test
```

Do not make every test an end-to-end test.

## 5. Hands-on lab

Create tests for Task Desk:

1. TaskService business rule;
2. route reaches handler;
3. authorization rejects an unauthorized request;
4. task creation emits the expected event;
5. persistence writes the expected data.

## 6. Break/fix lab

Introduce a route collision, a broken binding, and a failing business rule. Write a regression test for each discovered bug.

## 7. Coverage mindset

A high percentage number does not guarantee a good test suite. Test boundaries, failure behavior, security decisions, and important state transitions.

## 8. Kernel Hacker

Trace one Parikshak test from test declaration to the framework bootstrap and assertion mechanism.

## Checkpoint

> **Testing is not a chapter at the end of the project; it is how we prove that each SPP abstraction behaves as expected.**
