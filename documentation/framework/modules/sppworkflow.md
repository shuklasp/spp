# Core Module: SppWorkflow

SppWorkflow is the framework's enterprise-grade workflow orchestration engine. It provides high-performance state machine management, multi-state parallel markings, automated Saga compensations, interactive wizards, and multi-tier organizational approval hierarchies.

---

## 1. Basic Philosophy

SppWorkflow follows the **"Unified Auditing & Deterministic State"** philosophy. Every state transition is strictly evaluated against configuration, protected by dynamic guards, and permanently logged with rich context evidence to guarantee compliance and predictability across complex business workflows.

---

## 2. Architecture

The module functions as an advanced state orchestrator powered by YAML definitions (`workflows.yml`) and database history tables (`spp_entity_workflow_history`).

### Key Components:
*   **WorkflowManager**: The central engine managing state evaluations, transitions, dynamic guards, and events (`workflow.before_transition`, `workflow.after_transition`).
*   **Parallel Markings**: Natively tracks entities existing in multiple non-linear states simultaneously (e.g., `['payment_verified', 'warehouse_packing']`).
*   **Saga Rollback Engine**: Inspects transition history to automatically fire compensating transactions (`rollback()`) when reverting complex distributed actions.
*   **WizardController**: Base controller providing step-by-step interactive wizard routing with HTMX external partials (**Zero Inline HTML Literals**).
*   **ApprovalChain**: Enterprise hierarchy manager evaluating financial and organizational thresholds for multi-tier sign-offs.

---

## 3. API & Usage

### Backend: Applying a Transition
```php
use SPP\Core\WorkflowManager;

// Apply transition with custom audit context evidence
WorkflowManager::applyTransition(
    $entity, 
    'published', 
    $currentUser, 
    'Editorial review passed.', 
    ['ip' => $_SERVER['REMOTE_ADDR'], 'document_id' => 84920]
);
```

### Backend: Automated Saga Rollback
```php
use SPP\Core\WorkflowManager;

// Reverts the last state transition and automatically triggers any compensating callbacks
WorkflowManager::rollback(
    $entity, 
    $currentUser, 
    ['reason' => 'Fulfillment failure, restocking items.']
);
```

### Backend: Organizational Approval Chain
```php
use SPP\Core\Workflow\ApprovalChain;

$chain = ApprovalChain::createFromWorkflow('expense');
$requiredTier = $chain->evaluateRequiredTier($expenseEntity, 'amount');

if ($requiredTier) {
    $chain->approve($expenseEntity, $currentUser, $requiredTier, 'pending_director', 'Approved by manager.');
}
```

---

## 4. CLI Administration

SppWorkflow includes dedicated CLI tooling for schema provisioning, visual diagram generation, and automated SLA escalations:

*   `php spp.php config:sync workflows`: Provisions database schemas (`spp_workflows`, `spp_entity_workflow_history`) and synchronizes active YAML definitions.
*   `php spp.php workflow:dump <entity_type.bundle>`: Generates visual state machine graphs in **Mermaid.js** or **Graphviz DOT** format.
*   `php spp.php workflow:process-timeouts`: Asynchronous daemon evaluating entities past their configured SLA timeout windows and firing automated escalation transitions.

---
[Back to Modules Index](index.md)
