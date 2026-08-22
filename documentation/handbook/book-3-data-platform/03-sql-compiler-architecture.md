# Book 3 Chapter 3 — SQL Compiler Architecture

## 1. Why compile SQL?

A database abstraction can express an operation without hard-coding the exact SQL syntax for one database engine.

The framework then needs a transformation step:

```mermaid
flowchart LR
    A[Application data operation] --> B[SPPDB representation]
    B --> C[SQL compiler]
    C --> D[Database-specific SQL]
    D --> E[Driver / database]
```

## 2. Compiler versus driver

These are different responsibilities.

**Compiler:** turns the framework's query representation into SQL appropriate to a dialect.

**Driver:** provides the connection/execution boundary for the database backend.

A simplified model is:

```text
query representation
      ↓
compiler
      ↓
SQL + parameters
      ↓
driver/connection
      ↓
database
```

## 3. Why this is useful

Without a compiler architecture, backend-specific SQL can leak throughout application code.

With a compiler architecture, the application can describe an operation at a higher level while backend-specific differences stay below the application boundary.

## 4. Source-synchronized note

The current SPPDB implementation contains explicit compiler/driver architecture. This is a significant change from a simplistic mental model of “SPPDB is just PDO.”

## 5. Hands-on lab

Take one Task Desk query and trace it from application code through the SPPDB representation into compiled SQL.

Record:

- input/query representation;
- compiler stage;
- generated SQL;
- parameters;
- driver execution.

## 6. Failure lab

Introduce a construct unsupported by the selected dialect or compiler and identify whether the failure occurs during query construction, compilation, or execution.

## 7. Kernel Hacker

Trace the compiler interface and its implementation hierarchy. Compare the common compilation path with a backend-specific compiler.

## Checkpoint

> **A compiler keeps SQL dialect knowledge in the data infrastructure instead of spreading it through application code.**
