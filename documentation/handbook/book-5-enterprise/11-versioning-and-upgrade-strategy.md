# Book 5 Chapter 11 — Versioning and Upgrade Strategy

## 1. Why upgrades need architecture

A framework upgrade can affect:

- public APIs;
- configuration;
- CLI commands;
- generated artifacts;
- module contracts;
- runtime behavior;
- browser assets;
- database/migration assumptions.

## 2. Source-synchronized documentation

This V3 handbook is tied to a source baseline. When the source changes, documentation must be re-audited rather than assumed current.

## 3. Upgrade workflow

```mermaid
flowchart LR
    A[New SPP source] --> B[Changed implementation surface]
    B --> C[Documentation impact analysis]
    C --> D[Test/lab verification]
    D --> E[Handbook update]
    E --> F[Application upgrade]
```

## 4. Hands-on lab

Choose one SPP subsystem and compare its source at two revisions. Record:

- changed public API;
- changed behavior;
- affected handbook chapters;
- affected labs/tests.

## Checkpoint

> **Framework upgrades are source/API changes that must be evaluated against application behavior, not just dependency version numbers.**
