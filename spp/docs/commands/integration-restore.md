## integration:restore

**Purpose**: Time-travel a user state to a historical point using CQRS Event Sourcing.

### Synopsis
```bash
php spp.php integration:restore <user_id> <timestamp_or_snapshot_id>
```

### Extended Usage
The `integration:restore` command leverages SPP's internal CQRS Event Store to provide point-in-time recovery for integrated data. 

If an administrator accidentally corrupts a user's profile in Joomla, or a webhook sends malformed data that overwrites a user's email across the Data Mesh, you can use this command. It queries the `EventStore` for the user's exact state at the requested historical timestamp, extracts the payload, and re-broadcasts it through the Saga Orchestrator. This forces all integrated applications to instantly sync backward to the correct historical state.

### Options Available
*   `user_id` (integer, required): The ID of the user to restore.
*   `timestamp_or_snapshot_id` (string, required): The historical Unix timestamp or specific CQRS Snapshot ID to revert to.

### Under the Hood Activity
*   **Filesystem Writes**: None.
*   **DB Interactions**: Queries the CQRS `EventStore` tables for historical snapshots.
*   **Outbound HTTP Calls**: None directly (delegates broadcast to the DAG Orchestrator).
