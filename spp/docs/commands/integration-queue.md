## integration:queue:work

**Purpose**: Run the persistent CDC integration event queue worker.

### Synopsis
```bash
php spp.php integration:queue:work
```

### Extended Usage
The `integration:queue:work` command is a persistent daemon that handles Path 3 CDC (Change Data Capture). When an external application (like phpBB) is installed on the same database but is not routed through SPP, it cannot use the `IntegrationGateway` middleware.

Instead, SPP generates SQL Triggers for the external app's database tables. When a change happens natively, the trigger inserts an event into `spp_integration_events`. This command runs continuously in the background, polling that table, extracting the events, and broadcasting them through the Data Mesh using the Saga Orchestrator.

### Options Available
None.

### Under the Hood Activity
*   **Filesystem Writes**: None.
*   **DB Interactions**: Continuously polls the `spp_integration_events` table for pending records and updates their status to `processed`.
*   **Outbound HTTP Calls**: None directly (delegates broadcast to the DAG Orchestrator).
