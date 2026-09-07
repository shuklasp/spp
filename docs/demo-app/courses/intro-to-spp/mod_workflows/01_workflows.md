---
title: Designing Resilient State Machines
duration: 25 mins
summary: Master workflow states, transitions, Saga compensations, and audit trails.
---

# Designing Resilient State Machines

In enterprise architectures, entities move through complex lifecycles (e.g. `draft` → `pending_review` → `published`).

## 1. Native Entity Workflow APIs

All database models extending `SPPEntity` natively inherit workflow orchestration:

```php
// Check if state transition is allowed
if ($entity->canTransition('approve')) {
    $entity->applyTransition('approve', $currentUser, 'Passed code review');
}
```

## 2. Saga Pattern & Compensating Transactions

When multi-step workflows experience failure, the Saga orchestrator rolls back state using compensating callbacks registered in `WorkflowManager::rollback()`.

## 3. Dual Event Bus Firing

Workflow transitions dispatch events to both the event bus and hook pipelines:
- `\SPP\SPPEvent::fireEvent('workflow.after_transition', $payload)`
- `\SPP\SPPEvent::triggerHook('workflow.after_transition', $payload)`