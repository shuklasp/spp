## sppreport:cron

**Purpose**: Trigger SPP Report threshold alerts, scheduled jobs, webhooks, and automated email deliveries securely with distributed mutex locks.

### Synopsis
```bash
php spp.php sppreport:cron
```

### Extended Usage
The `sppreport:cron` command is designed to be executed via system crontab (e.g., `* * * * * php /path/to/spp.php sppreport:cron`) or triggered manually by administrators to evaluate all configured BI reports in `etc/sppreports`. It inspects each report's schedule against the current time, executes queries, checks threshold criteria, dispatches webhooks, and transmits email attachments (CSV or PDF).

### Options Available
- This command currently takes no additional options. It automatically discovers all report configurations located in `etc/sppreports/*.yml` and `etc/sppreports/*.json`.

### Under the Hood Activity
1. **Strict CLI Guarding**: Overrides `isCLIOnly()` to ensure execution is blocked from web contexts (`PHP_SAPI !== 'cli'`).
2. **Distributed Mutex Locking**: Invokes `\SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock()` prior to execution and guarantees `releaseDeploymentLock()` in a `finally` block to prevent race conditions during concurrent cron triggers.
3. **Filesystem Reads/Writes**: Discovers and parses report definition files in `etc/sppreports/`. Temporary buffer streams are utilized for CSV/PDF attachment encoding.
4. **Database Interactions**: Instantiates `SPPReport` engine and executes read-only query streams via `ReportQueryBuilder` against native SPP databases or configured external DSNs.
5. **Outbound HTTP Calls**: Upon matching `webhook_condition` thresholds, initiates outbound cURL POST requests containing JSON payloads to configured `webhook_url` endpoints.
