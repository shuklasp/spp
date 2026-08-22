# Runnable Lab Coverage Audit

## Purpose

A V3 chapter is not considered practically complete merely because explanatory prose exists. A runnable learning path should let a learner:

**read → build → run → test → break → diagnose → inspect source**.

## Current coverage model

| Area | Beginner build | Test | Failure lab | Source trace |
|---|---|---|---|---|
| Foundations | Yes | Planned per chapter | Yes | Yes |
| Containers / DI | Yes | Required | Required | Yes |
| Routing | Yes | Required | Required | Yes |
| Middleware | Yes | Required | Required | Yes |
| Events | Yes | Required | Required | Yes |
| Modules | Yes | Required | Required | Yes |
| SPPDB | Yes | Required | Required | Yes |
| SPPReport | Yes | Required | Required | Yes |
| LiveComponent | Yes | Required | Required | Yes |
| SPPLive | Yes | Required | Required | Yes |
| SPPUX | Yes | Required | Required | Yes |
| Enterprise integration | Yes | Required | Required | Yes |

## Five priority subsystems

For the 2026 source changes, the highest-priority labs are:

### LiveComponent

Build a small component that demonstrates:

- public state;
- hydration/dehydration;
- lifecycle hooks;
- validation;
- action handling;
- rendering;
- integrity/error behavior where supported.

### SPPLive

Use one LiveComponent and compare transport/engine behavior without rewriting the component itself.

### SPPUX

Start with a server-rendered page, mount the client runtime, and trace browser initialization and application-aware asset/runtime paths.

### SPPDB

Build the same data operation through the supported SPPDB abstraction and then trace the driver/compiler path.

### SPPReport

Create a report against a known schema, inspect the schema/validation stage, execute it, and deliberately feed an invalid or unsafe input where the implementation supports that diagnostic exercise.

## Definition of done

A runnable lab is complete only when its expected result is reproducible from the documented source baseline and the failure mode is specific enough to diagnose without guessing.