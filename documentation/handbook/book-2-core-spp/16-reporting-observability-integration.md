# Book 2 Chapter 16 — Reporting and Observability Integration

## Why reporting belongs beyond presentation

A report is not merely a formatted table. It can involve:

- data acquisition;
- query construction;
- schema knowledge;
- validation;
- execution;
- formatting;
- delivery;
- auditing.

SPPReport is covered deeply in Book 3.

## Observability

Operational systems also need to explain what happened:

```text
request
 → application
 → data/reporting
 → log/audit/diagnostic record
```

Keep user-facing reporting separate from operational telemetry.

## Hands-on lab

Create an operator report for failed Task Desk jobs and include enough diagnostic information to investigate the failure without exposing secrets.

## Failure lab

Introduce a slow/failing data source and document which diagnostic signals are available.
