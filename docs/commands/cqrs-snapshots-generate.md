## cqrs:snapshots:generate

**Purpose**: Scan active CQRS event streams, replay event logs, and generate point-in-time state snapshots for workflow entities to optimize future reconstitution.

### Synopsis

```bash
php spp.php cqrs:snapshots:generate
```

### Extended Usage

The `cqrs:snapshots:generate` command inspects the configured event store directory (`var/cqrs/events_*.jsonl` or `spp_cqrs_events` table). For each aggregate root entity discovered, it replays the recorded transition history in sequential order to determine the final cumulative state, then writes a snapshot record. Subsequent reads can load this snapshot directly rather than replaying the entire event stream from genesis.

Example:
```bash
php spp.php cqrs:snapshots:generate
```

### Options Available

- This command currently takes no additional options. It automatically processes all event stream logs in the active environment.

### Under the Hood Activity

1. **Filesystem Reads / DB Interaction**: Scans `var/cqrs/events_*.jsonl` flat files or queries `spp_cqrs_events` to discover entity IDs and their historical event streams.
2. **State Reconstitution**: Iterates through each event payload in chronological order, merging state properties into a unified state array.
3. **Filesystem Writes / DB Interaction**: Stores the cumulative state and `last_event_index` into `var/cqrs/snapshots/snapshot_<entityType>_<id>.json` or upserts into the `spp_cqrs_snapshots` database table.
4. **Outbound HTTP Calls**: None. All snapshot operations execute completely locally within the persistence layer.
