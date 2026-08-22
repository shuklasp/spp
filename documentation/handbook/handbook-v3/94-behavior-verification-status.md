# 94 — Behavioral Verification Status

## Purpose

This page records what can and cannot be claimed after a repository-level documentation audit.

The handbook distinguishes three things:

1. **Source verified** — the implementation or configuration is present in the repository.
2. **Behavior evidenced** — tests, fixtures, generated documentation, or executable project artifacts demonstrate the behavior.
3. **Execution verified in this documentation pass** — the behavior was actually run during the current documentation maintenance session.

The third category must never be inferred from the first two.

## Current status

| Area | Source | Repository evidence | Executed in this pass | Documentation rule |
|---|---|---|---|---|
| SPPDB | Verified | Source + generated API material | Not claimed unless a runnable test was executed | Explain architecture; do not claim benchmark results. |
| SPPReport | Verified | Source + generated API material | Not claimed unless a runnable test was executed | Treat as a reporting/data-access subsystem, not a generic report writer. |
| LiveComponent | Verified | Source + handbook/reference material | Not claimed unless a runnable test was executed | Teach lifecycle, state integrity, hydration/dehydration, and hooks. |
| SPPLive | Verified | Source + module/docs material | Not claimed unless a runnable test was executed | Teach engine selection/transport boundaries only where supported by source. |
| SPPUX | Verified | Source + module/docs material | Not claimed unless a runnable test was executed | Teach runtime/bootstrap/mounting responsibilities without inventing browser guarantees. |

## How to turn evidence into a stronger claim

Use this ladder:

```text
source exists
    ↓
configuration/activation exists
    ↓
test or fixture demonstrates behavior
    ↓
reproducible lab command exists
    ↓
current run succeeds
    ↓
handbook may state the observed behavior confidently
```

A source-only feature should be described as **implemented**, not as **proven in production**.

## Lab reporting format

Every runnable lab should record:

- source baseline/ref;
- prerequisites;
- exact command or procedure;
- expected result;
- observed result;
- failure mode, if any;
- cleanup/reset procedure;
- affected source path;
- date of verification.

## Conservative wording policy

Prefer:

> “The repository implements …”

when source evidence is available.

Prefer:

> “The test fixture demonstrates …”

when a test proves behavior.

Prefer:

> “This documentation pass executed … successfully.”

only when the behavior was actually executed during the maintenance pass.

Avoid unsupported statements such as:

- “guarantees”;
- “always”;
- “production-ready”;
- “scales indefinitely”;
- “exactly once”;
- “transaction-safe”;
- “secure by default”;

unless the repository evidence specifically supports the statement.
