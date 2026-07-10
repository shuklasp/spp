# 16. Enterprise Workflow Orchestration

SPP provides a high-performance, YAML-driven workflow engine (`SPPWorkflowManager`) capable of powering strict state machines, multi-state parallel markings, interactive multi-step wizards, and multi-tier organizational approval hierarchies.

---

## Core Concepts

| Term | Description |
| :--- | :--- |
| **WorkflowManager** | Central orchestrator for evaluating state transitions, dynamic guards, and events. |
| **Parallel Markings** | Support for entities occupying multiple concurrent states simultaneously. |
| **Saga Pattern** | Automated compensating transactions (`rollback()`) to gracefully revert failed workflows. |
| **WizardController** | Abstract base controller for step-by-step interactive wizards using HTMX external partials. |
| **ApprovalChain** | Organizational hierarchy manager supporting tier thresholds and dynamic approvers. |
| **Rich Auditing** | `spp_entity_workflow_history` table capturing transition timestamps, comments, and JSON context evidence. |

---

## Defining Workflows in YAML

Workflows are defined in `workflows.yml` files located in `APP_ETC_DIR` (`etc/apps/workflows/**/*.yml`) or `SPP_ETC_DIR` (`spp/etc/workflows.yml`). The engine automatically deep-scans these directories and caches definitions.

```yaml
# etc/apps/workflows/cms/articles.yml
node.article:
  description: "Standard editorial workflow for publishing articles"
  states:
    - draft
    - in_review
    - published
  transitions:
    submit:
      from: [draft]
      to: in_review
      permission: "content.submit"
    publish:
      from: [in_review]
      to: published
      guards: ["\\App\\Default\\Guards\\ContentGuard::validateCompleteness"]
```

---

## Applying Transitions

To transition an entity, call `WorkflowManager::applyTransition`. This automatically validates permissions, executes dynamic guards, fires lifecycle events, and records audit logs.

```php
use SPP\Core\WorkflowManager;

// Apply a transition with rich audit context
WorkflowManager::applyTransition(
    $entity, 
    'published', 
    $currentUser, 
    'Approved for release', 
    ['ip' => $_SERVER['REMOTE_ADDR'], 'document_id' => 12345]
);
```

---

## Multi-Step Wizards (`WizardController`)

The `WizardController` provides turn-key multi-step wizard routing. To adhere to SPP's **Zero Inline HTML Literals** architectural rule, it serves dynamic HTMX/Turbo external partials for each step.

```php
namespace App\Default\Controllers;

use SPP\Core\Workflow\WizardController;

class OnboardingWizardController extends WizardController
{
    protected string $entityType = 'wizard';
    protected string $bundle = 'onboarding';

    protected function getWizardEntity()
    {
        return $_SESSION['onboarding_entity'] ?? new \stdClass();
    }

    protected function saveWizardEntity($entity): void
    {
        $_SESSION['onboarding_entity'] = $entity;
    }
}
```

---

## Organizational Hierarchies (`ApprovalChain`)

The `ApprovalChain` orchestrates multi-tier approvals (`manager`, `director`, `finance`), automatically evaluating financial/quantity thresholds to determine necessary sign-offs.

```php
use SPP\Core\Workflow\ApprovalChain;

// Automatically builds the chain from workflows.yml definition
$chain = ApprovalChain::createFromWorkflow('expense');

// Evaluates which tier requires approval based on expense amount
$requiredTier = $chain->evaluateRequiredTier($expenseEntity, 'amount');

if ($requiredTier) {
    $chain->approve($expenseEntity, $currentUser, $requiredTier, 'pending_director', 'Manager sign-off complete.');
}
```

---

## CLI Tooling

SPP provides three high-powered CLI commands for workflow administration:

1. **`php spp.php config:sync workflows`**: Synchronizes YAML definitions to `spp_workflows` and provisions `spp_entity_workflow_history`.
2. **`php spp.php workflow:dump <entity_type.bundle>`**: Generates visual state machine diagrams in Mermaid.js or Graphviz DOT format.
3. **`php spp.php workflow:process-timeouts`**: Asynchronous daemon that monitors SLA timeouts and automatically fires escalation transitions.

---

## Native Entity Workflow APIs

All database models extending `\SPPMod\SPPDB\SPPEntity` natively inherit comprehensive workflow capabilities. Rather than directly setting or manipulating raw status properties, always utilize the native fluent methods:

- `$entity->getWorkflowState()`: Retrieves current state markings (including parallel states).
- `$entity->canTransition($transitionName)`: Checks if a transition is valid from the current state.
- `$entity->applyTransition($transitionName, $user, $comment, $contextData)`: Executes a transition, evaluates guards, triggers event hooks, and logs audit history.
- `$entity->getWorkflowHistory()`: Retrieves the lineage of state changes from `spp_entity_workflow_history`.

---

## Controller Transition Helpers & Smart Content Negotiation

In any controller extending `ViewController`, `ResourceController`, or `WizardController`, you can avoid manual transition checks and custom response formatting by using the native helper:

```php
$this->transitionEntity($entity, $transitionName, $contextData, $successView, $errorView);
```

This helper automatically applies the transition and performs smart content negotiation:
- **HTMX Requests (`HX-Request`)**: Directly resolves and serves external HTML/PHP partials (`partials/{entity}_row.php`, etc.).
- **Turbo Streams (`Turbo-Frame`)**: Automatically streams real-time updates using standalone Turbo Stream templates (`streams/update.php`).

---

## Dual Event Bus Firing

When evaluating state changes, `SPPWorkflowManager` guarantees full coverage for all event subscribers by simultaneously firing both `\SPP\SPPEvent::fireEvent()` and `\SPP\SPPEvent::triggerHook()` for:
- `workflow:before_transition` (and `workflow.before_transition`)
- `workflow:after_transition` (and `workflow.after_transition`)

---

## Workflow Form Guards

When building forms or update endpoints for workflow-managed entities, `SPPWorkflowGuardValidator` (or `SPP_Validator_WorkflowGuardValidator`) can be included in form validation rules to prevent unauthorized modifications on locked, pending, or in-flight entities.

---

## Local Standalone Client Assets

To ensure maximum performance and security without relying on external CDNs, SPP bundles local standalone client asset distributions for HTMX and Turbo Streams. Include them directly in your base layouts:

```html
<script src="/spp/admin/js/htmx.min.js"></script>
<script src="/spp/admin/js/turbo-streams.min.js"></script>
```
