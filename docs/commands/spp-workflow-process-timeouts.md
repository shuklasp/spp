## workflow:process-timeouts

**Purpose**: Automatically evaluate entities lingering in workflow states past their configured SLA windows and apply automated fallback/escalation transitions.

### Synopsis
```bash
php spp.php workflow:process-timeouts
```

### Extended Usage
The `workflow:process-timeouts` command acts as an asynchronous SLA daemon. It queries the `spp_entity_workflow_history` table to locate entities whose last transition timestamp exceeds the `timeout` duration defined in `workflows.yml` (e.g., `timeout: 48 hours`). It then automatically invokes `WorkflowManager::applyTransition()` to progress the entity along the specified `timeout_transition` path (e.g., escalating an unreviewed expense report to a VP or notifying compliance).

### Options Available
- None. This command operates globally across all registered workflow definitions.

### Under the Hood Activity
- **SAPI Guarding**: Strictly guarded by `isCLIOnly()` to ensure execution is blocked from web server contexts.
- **Database Evaluation**: Queries `spp_entity_workflow_history` using `SPPDB` to compare transition timestamps against the current system time minus the timeout interval.
- **Entity Loading & Escalation**: Loads the target entity via `SPPEntity::load()` and invokes `WorkflowManager::applyTransition()`, triggering all standard events, dynamic guards, and audit history tracking.
