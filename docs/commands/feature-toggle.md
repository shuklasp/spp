## feature:toggle

**Purpose**: Manage advanced feature flags, canary rollout percentages, and evaluate Telemetry Kill Switch status.

### Synopsis

```bash
php spp.php feature:toggle [--flag=<name>] [--enable=true|false] [--canary=<percentage>]
```

### Extended Usage

The `feature:toggle` command provides enterprise deployment decoupling. It allows systems administrators and developers to enable or disable advanced features dynamically, rollout experimental features to a percentage of active users (canary releases), and monitor the real-time status of the automated Telemetry Kill Switch.

Example:
```bash
php spp.php feature:toggle --flag=new_checkout_flow --enable=true --canary=50
```

### Options Available

- `--flag=<name>`: Exact name of the feature flag to target.
- `--enable=<true|false>`: Explicitly enable or disable the feature flag.
- `--canary=<percentage>`: Set the percentage of traffic (0-100) to expose to the canary release.

### Under the Hood Activity

1. **Strict SAPI Guarding**: Validates that execution occurs in a secure CLI environment via `isCLIOnly(): bool`.
2. **Dynamic Flag Mutation**: Updates the in-memory or persisted configuration state within `FeatureManager`.
3. **Canary Hashing**: Uses deterministic hashing (`crc32`) of the flag name and user ID to ensure consistent canary routing.
4. **Telemetry Kill Switch Check**: Queries `OpenTelemetryExporter` for active error counts associated with the flag scope. If errors exceed the threshold, the flag is marked as `TRIGGERED` and automatically disabled.
