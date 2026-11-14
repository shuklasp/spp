## workflow:process-timeouts

**Purpose**: Daemon routine that scans active entity SLA deadlines, processes expired timeout thresholds, and automatically triggers escalation transitions.

### Synopsis

```bash
php spp.php workflow:process-timeouts [--dry-run]
```

### Extended Usage

The `workflow:process-timeouts` command enforces Service Level Agreements (SLAs) across workflow-enabled domain entities. When an entity transitions into a state with a declared `timeout` and `timeout_transition`, the workflow engine registers an active deadline in the `spp_workflow_slas` table. This command inspects expired deadlines, checks whether the entity remains in the target state, and applies the configured escalation transition.

Example:
```bash
# Execute timeout processor as a cron job or background daemon
php spp.php workflow:process-timeouts
```

### Options Available

- `--dry-run`: Inspect expired deadlines and log pending escalations without applying mutations to entity state or history.

### Under the Hood Activity

1. **SLA Deadline Query**: Queries `spp_workflow_slas` where `deadline_at <= UNIX_TIMESTAMP()`.
2. **Entity Hydration**: Instantiates target domain entities extending `\SPPMod\SPPDB\SPPEntity` by primary key.
3. **State Guard Evaluation**: Verifies that the entity's current state still matches the SLA record's registered state.
4. **Automated Escalation Transition**: Invokes `$entity->applyTransition($timeoutTransition, 'system_sla_daemon', 'Automated SLA timeout escalation')`.
5. **SLA Record Purge**: Automatically purges processed records from `spp_workflow_slas`.
