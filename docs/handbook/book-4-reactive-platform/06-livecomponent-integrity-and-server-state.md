# Book 4 Chapter 6 — LiveComponent Integrity and Server-Side State

## 1. Integrity is not authorization

A state checksum can tell the runtime whether state changed unexpectedly.

It cannot answer:

> Is this user allowed to perform this operation?

Keep the boundaries distinct:

```text
Integrity      → was the carried state altered?
Validation     → is the value acceptable?
Authorization  → may this caller perform the operation?
Business rules → is the operation valid in the domain?
```

## 2. Why this matters

A reactive component combines all four concerns at one interaction boundary. That makes the architecture powerful but easy to misuse.

## 3. Hands-on security lab

Create four tests around one Task Desk action:

1. valid state + authorized user;
2. valid state + unauthorized user;
3. altered state;
4. intact state containing an invalid business value.

The expected failures should occur at different conceptual layers.

## 4. Failure diagnosis

When a LiveComponent request fails, record:

```text
transport received?
integrity accepted?
state hydrated?
action authorized?
input valid?
business operation valid?
render successful?
```

## Checkpoint

> **Reactive systems are secure when integrity, validation, authorization, and business rules remain explicit separate boundaries.**
