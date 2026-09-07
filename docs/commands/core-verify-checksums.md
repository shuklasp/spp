## `core:verify-checksums`

**Purpose**: Verify cryptographic SHA-256 integrity of core SPP framework files against an authenticated baseline manifest.

### Synopsis
```bash
php spp.php core:verify-checksums [OPTIONS]
```

### Extended Usage
The `core:verify-checksums` command computes cryptographic SHA-256 signatures for all core framework files and validates them against the baseline manifest stored in `spp/etc/checksums.json`. This ensures that core engine code has not been altered through supply-chain attacks, unintentional local hacks, incomplete rsync/FTP updates, or filesystem tampering.

Practical examples:
```bash
# Verify framework integrity against recorded manifest
php spp.php core:verify-checksums

# Generate or update the authenticated framework checksum manifest
php spp.php core:verify-checksums --generate

# Strict mode: fail immediately if any untracked or modified files exist
php spp.php core:verify-checksums --strict

# Machine-readable output for deployment validation
php spp.php core:verify-checksums --json
```

### Options Available
- `--generate`: Generate a fresh `spp/etc/checksums.json` baseline containing SHA-256 signatures of all core engine files.
- `--strict`: Strict mode. Causes the command to exit with code 1 if untracked files are detected in core directories.
- `--json`: Output full file verification results as structured JSON.

### Under the Hood Activity
- Scans `spp/core/`, `spp/modules/spp/`, `spp/sppinit.php`, and `spp/spp.php`.
- Computes cryptographic SHA-256 checksums (`hash_file('sha256', ...)`) for each PHP script.
- Writes or validates against `spp/etc/checksums.json`.
- Reports categorized status for each file: `[MATCHED]`, `[MODIFIED]`, `[MISSING]`, or `[UNTRACKED]`.
