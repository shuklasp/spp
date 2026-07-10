## crypto:shard:generate

**Purpose**: Generate Shamir Secret key shards across vault nodes and verify Zero-Knowledge MPC signing.

### Synopsis

```bash
php spp.php crypto:shard:generate [--shares=<N>] [--threshold=<M>] [--key=<id>]
```

### Extended Usage

The `crypto:shard:generate` command implements enterprise vault security and eliminates single points of compromise for API secrets and database passwords. By leveraging Shamir's Secret Sharing polynomial mathematics and Multi-Party Computation (MPC), it splits master private keys across isolated vault nodes. Cryptographic signatures are generated collaboratively through partial share evaluations, ensuring the master private key is never assembled in plain text inside server memory.

Example:
```bash
php spp.php crypto:shard:generate --shares=7 --threshold=4 --key=billing_master_private_key
```

### Options Available

- `--shares=<N>`: Total number of mathematical polynomial key shares to generate across isolated vault nodes. Defaults to `5`.
- `--threshold=<M>`: Minimum threshold of participating vault nodes required to perform a valid cryptographic signing operation. Defaults to `3`.
- `--key=<id>`: Master key identifier string to shard. Defaults to `enterprise_master_secret`.

### Under the Hood Activity

1. **Strict SAPI Guarding**: Validates secure CLI execution via `isCLIOnly(): bool`.
2. **Polynomial Generation**: Evaluates an `M-1` degree polynomial over finite fields to construct `N` unique mathematical share points (`x, f(x)`).
3. **Vault Distribution**: Stores cryptographic share hashes securely within isolated simulated vault node structures.
4. **Threshold MPC Verification**: Simulates a Multi-Party Computation protocol where `M` participating nodes provide partial HMAC evaluations of a payload, aggregating them into a single valid master signature while preserving absolute zero-knowledge security.
