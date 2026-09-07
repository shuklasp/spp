# Book 2 Chapter 12 — Workflow and Approval Architecture

## 1. CRUD state versus business process

A field such as `status = approved` is not automatically a workflow.

A workflow describes a process with actors, transitions, conditions, and consequences.

```mermaid
flowchart LR
    A[Draft] --> B[Submitted]
    B --> C[Manager approval]
    C --> D[Finance approval]
    D --> E[Approved]
```

## 2. Why a framework workflow subsystem exists

Complex approval processes otherwise spread rules across controllers, scheduled jobs, notifications, and database flags.

A workflow subsystem gives the process a recognizable place in the architecture.

## 3. SPP workflow architecture

The repository contains workflow orchestration and approval-chain/wizard material. Exact classes and commands are maintained in the module/reference documentation.

## 4. Events and workflow

A transition can have application consequences:

```text
Approved
   ↓
workflow transition
   ↓
SPP event
 ┌─┼─────────┐
 ↓ ↓         ↓
audit notify reporting
```

The workflow decides the process transition; events decouple reactions.

## 5. Hands-on lab

Build Purchase Approval:

- Draft;
- Submitted;
- Manager review;
- Finance review;
- Approved or Rejected.

Add role-based authorization and audit records.

## 6. Failure lab

Try:

- an invalid transition;
- an unauthorized approver;
- a repeated approval;
- a timed-out operation where the implementation supports timeout processing.

Trace whether the failure is in authorization, workflow transition, persistence, or event processing.

## 7. When not to use workflow

Do not create a workflow object for a simple boolean or status field when there is no process, actor, transition rule, or lifecycle concern.

## Checkpoint

> **Workflow is explicit business-process state and transition management, not merely a collection of status strings.**
