## `polyglot:async`

**Purpose**: Execute managed asynchronous polyglot jobs and background queue tasks.

### Synopsis
```bash
php spp.php polyglot:async <job_id|payload_base64>
```

### Extended Usage
```bash
# Execute a managed job registered by ID in the atomic job ledger
php spp.php polyglot:async poly_7b8a1c93f019284e

# Legacy execution with base64 serialized payload
php spp.php polyglot:async eyJsYW5nIjoicHl0aG9uIiwibW9kdWxlIjoiY2FsYy5weSIsImZ1bmMiOiJhZGQiLCJhcmdzIjpbMSwyXX0=
```

### Options Available
- `<job_id|payload_base64>`: Unique job identifier (`poly_*`) or base64 encoded execution payload.

### Under the Hood Activity
- Loads persistent job state from `var/jobs/polyglot/{job_id}.json`.
- Transitions job lifecycle state (`queued` -> `running` -> `completed` / `failed`).
- Injects and propagates W3C Trace Context headers for distributed OpenTelemetry observability.
- Automatically handles retries with exponential backoff on transient worker failures.
- Records structured execution results or exception stack traces in the persistent ledger.
