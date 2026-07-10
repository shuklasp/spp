## cqrs:webhooks:dispatch

**Purpose**: Dispatch pending transactional outbox webhooks to subscribers with HMAC-SHA256 signature verification and exponential backoff retries.

### Synopsis

```bash
php spp.php cqrs:webhooks:dispatch [--batch=<batch_size>]
```

### Extended Usage

The `cqrs:webhooks:dispatch` command operates as a reliable background worker daemon that processes domain events logged in the CQRS Event Store Outbox table. It guarantees at-least-once delivery to registered external microservice subscribers, generating cryptographic signatures (`X-SPP-Signature`) to ensure payload authenticity.

Example:
```bash
php spp.php cqrs:webhooks:dispatch --batch=100
```

### Options Available

- `--batch=<batch_size>`: Number of pending outbox webhooks to process per dispatch cycle. Defaults to `50`.

### Under the Hood Activity

1. **Transactional Outbox Query**: Inspects `\SPPMod\SPPWorkflow\CQRS\OutboxWebhookDispatcher` for pending webhook items whose `next_retry` timestamp has passed.
2. **Cryptographic Signing**: Computes an HMAC-SHA256 signature (`X-SPP-Signature: sha256=...`) using the application secret.
3. **Non-Blocking Network Execution**: Initiates non-blocking cURL calls with strict timeouts to deliver JSON payloads to target subscriber URLs.
4. **Exponential Backoff & DLQ Escalation**: On failure, calculates the next retry interval using exponential backoff (`2^attempts * 15s`). If delivery fails after 5 attempts, transitions the item to the Dead Letter Queue (`dlq`) for administrative escalation.
