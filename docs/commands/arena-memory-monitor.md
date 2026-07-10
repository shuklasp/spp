## arena:memory:monitor

**Purpose**: Monitor active MemoryArena worker allocations and trigger instantaneous zero-overhead resets.

### Synopsis

```bash
php spp.php arena:memory:monitor [--arena=<name>] [--capacity=<MB>] [--iterations=<N>]
```

### Extended Usage

The `arena:memory:monitor` command introduces high-performance, zero-allocation memory arena mechanics to long-running PHP CLI background daemons. Instead of relying on traditional PHP garbage collection—which causes periodic execution pauses and gradual memory fragmentation over months of continuous uptime—daemons pre-allocate contiguous memory arenas. Workers allocate objects within the arena during a job and instantly reset the arena offset pointer to zero upon completion.

Example:
```bash
php spp.php arena:memory:monitor --arena=spp_outbox_arena --capacity=128 --iterations=5
```

### Options Available

- `--arena=<name>`: Name of the MemoryArena instance to initialize and monitor. Defaults to `spp_cqrs_worker_arena`.
- `--capacity=<MB>`: Total contiguous memory capacity in megabytes to pre-allocate for the arena pool. Defaults to `64`.
- `--iterations=<N>`: Number of simulated worker job cycles to execute and reset. Defaults to `3`.

### Under the Hood Activity

1. **Strict SAPI Guarding**: Validates secure CLI execution via `isCLIOnly(): bool`.
2. **Arena Pre-Allocation**: Reserves a contiguous memory block in user space corresponding to the requested capacity in megabytes.
3. **Offset Tracking**: Allocates worker objects (`payload_json`, `entity_snapshot`, `w3c_trace_spans`) by advancing an internal byte offset pointer, completely bypassing traditional Zend engine heap allocation overhead.
4. **Pointer Resets**: Upon worker job completion, instantaneously resets the allocation offset pointer back to zero. This effectively wipes all temporary job objects from memory in `O(1)` time without triggering a single garbage collection cycle.
