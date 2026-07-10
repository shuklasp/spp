## db:pool:orchestrate

**Purpose**: Autonomous AI-guided database connection pool manager and dynamic query rerouter.

### Synopsis

```bash
php spp.php db:pool:orchestrate
```

### Extended Usage

The `db:pool:orchestrate` command acts as an autonomous database administrator. It monitors active PDO connection pools, evaluates query queue depths, and tracks transaction latency. When it detects lock contention or query bottlenecks on the primary master database, it consults `SPPAI` to determine the optimal connection scaling configuration and instantly reroutes read (`SELECT`) queries to healthy read-replica pools without dropping active user requests.

Example:
```bash
php spp.php db:pool:orchestrate
```

### Options Available

None. The command operates autonomously by continuously evaluating real-time pool metrics.

### Under the Hood Activity

1. **Strict SAPI Guarding**: Validates secure CLI execution via `isCLIOnly(): bool`.
2. **Metric Ingestion**: Gathers real-time connection counts, queue depths, and latency figures for all active database pools.
3. **AI Orchestration Consult**: When congestion is identified, compiles pool telemetry into an expert system prompt and invokes `SPPAI::callTool()` to request optimal connection scaling numbers and routing destinations.
4. **Dynamic Table Routing**: Updates active in-memory routing tables to point `SELECT` queries to read-replicas, scales active replica connection pools, and logs the full sequence of AI orchestration actions taken.
