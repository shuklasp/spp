## storage:crdt:sync

**Purpose**: Perform multi-region active-active CRDT synchronization and resolve write conflicts.

### Synopsis

```bash
php spp.php storage:crdt:sync [--local=<region>] [--remote=<region>]
```

### Extended Usage

The `storage:crdt:sync` command facilitates global high-availability active-active database cluster synchronization. Using Conflict-Free Replicated Data Type (CRDT) mechanics, it exchanges vector clocks and Last-Write-Wins (LWW) element registers between geographic regions to automatically reconcile concurrent updates without human intervention.

Example:
```bash
php spp.php storage:crdt:sync --local=us-east-1 --remote=eu-west-1
```

### Options Available

- `--local=<region>`: Target local geographic region ID. Defaults to `us-east-1`.
- `--remote=<region>`: Target remote geographic region ID. Defaults to `eu-west-1`.

### Under the Hood Activity

1. **Strict SAPI Guarding**: Validates secure CLI execution via `isCLIOnly(): bool`.
2. **Distributed Mutex Locking**: Acquires a distributed deployment lock via `TargetConnection::acquireDeploymentLock()` to guarantee zero concurrency race conditions during state replication.
3. **Vector Clock Merge**: Compares and merges region vector clocks, selecting the element-wise maximum.
4. **LWW Conflict Resolution**: Resolves concurrent element updates using floating-point timestamp comparison, falling back to lexicographical region comparison on tie-breakers. Safely releases the distributed lock upon completion.
