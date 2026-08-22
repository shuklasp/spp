# Book 5 Chapter 3 — Reporting Operations and Observability

A production report is part of an operational system, not merely a screen.

Consider:

```text
report request
 ↓
authorization
 ↓
data source
 ↓
query/validation
 ↓
execution
 ↓
result
 ↓
logging/audit/metrics where configured
```

## Lab

Create an operational report showing failed Task Desk background jobs and enough metadata to diagnose them.

## Design rule

Do not place secrets or unrestricted internal database structures into an operator report merely because the report engine can access them.
