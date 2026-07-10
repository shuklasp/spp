## chaos:inject

**Purpose**: Configure ChaosMonkey parameters and trigger resilience testing fault injections in staging environments.

### Synopsis

```bash
php spp.php chaos:inject [--enable=true|false] [--rate=<percentage>] [--test]
```

### Extended Usage

The `chaos:inject` command brings Chaos Engineering to the SPP framework. By enabling ChaosMonkey in staging or pre-production environments, systems architects can simulate real-world infrastructure instability (latency spikes, network jitter, cURL timeouts, and database connection drops) to empirically prove that fallback mechanisms, CQRS outbox retries, and DAG job failovers function flawlessly.

Example:
```bash
php spp.php chaos:inject --enable=true --rate=10 --test
```

### Options Available

- `--enable=<true|false>`: Explicitly enable or disable ChaosMonkey fault injection globally.
- `--rate=<percentage>`: Set the probability percentage (0-100) of injecting a fault during a request lifecycle. Defaults to `5`.
- `--test`: Immediately simulate and trigger a random fault injection directly in the CLI console.

### Under the Hood Activity

1. **Strict SAPI Guarding**: Ensures secure execution within a CLI context via `isCLIOnly(): bool`.
2. **State Configuration**: Updates the active runtime or persisted configuration inside `ChaosMonkey`.
3. **Probabilistic Evaluation**: Uses cryptographically secure random number generation (`random_int`) to evaluate if a fault should be injected based on the configured rate.
4. **Telemetry Span Recording**: When a fault is selected, starts an OpenTelemetry span (`chaos_injection.<fault_type>`) to track the exact failure path across W3C Trace Context.
