# Book 5 Chapter 1 — Workflow at Enterprise Scale

Enterprise workflow combines several SPP boundaries:

```text
identity
 ↓
authorization
 ↓
workflow transition
 ↓
event
 ↓
audit
 ↓
notification/reporting/background work
```

The key design rule is to keep the workflow itself explicit while letting events and background processing handle consequences where appropriate.

## Lab

Extend Purchase Approval with delegation, rejection, timeout handling where supported, audit entries, and operator visibility.

## Failure exercise

Create an invalid transition and an unauthorized transition. They should fail for different reasons.
