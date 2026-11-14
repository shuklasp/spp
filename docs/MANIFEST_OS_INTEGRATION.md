# SPP Manifest OS: Native Architectural Reference & Schema Standard

## 1. Foundational Concept: Why Per-Directory Manifests?

In large-scale enterprise frameworks, metadata about source code, application routing, contracts, security policies, and assets is traditionally stored in disconnected locations: docblock annotations, relational database tables, global configuration arrays, or CI/CD pipelines. This separation creates **configuration drift**, where the actual code on disk diverges from the metadata governing it.

**SPP Manifest OS** eliminates configuration drift by establishing **per-directory declarative manifests (`__spp_file_props.yml`)** as the single source of truth:
- Manifests travel alongside the code in Git repositories, branch merges, and server deployments.
- Every directory carries its own self-describing contract and declares the properties, stability, and capabilities of its contained files.
- Manifests are evaluated at runtime by security guards, autoloader compilers, testing engines, and administration workstations.

---

## 2. Complete Manifest YAML Schema (`__spp_file_props.yml`)

A canonical `__spp_file_props.yml` manifest contains three top-level sections: **Version**, **Directory Metadata**, and **File Declarations**. Custom root keys are strictly preserved during all framework operations.

```yaml
version: '1.0'

# Custom root extensions are strictly preserved
enterprise_custom_key: 'custom_metadata_value'

# Directory-level metadata describing the immediate folder
directory:
  description: 'Enterprise Student Management HTTP controllers and endpoints'
  usage: 'Dispatched by ApplicationRouter for /students routes'
  stability: 'stable'                # stable | beta | alpha | experimental | deprecated | internal
  author: 'Academic Engineering Team'
  review_date: '2027-01-01'           # Mandatory SLA review deadline (YYYY-MM-DD)
  security:
    roles: ['admin', 'registrar']
    allow_network: true              # true | false | array of allowed IP/CIDR/hostnames
    allow_filesystem: ['var/storage/students', 'tmp']
    allow_env: false
    allow_shell: false
    ip_allowlist: ['10.0.0.0/8', '192.168.1.0/24']
    rate_limit: 120                  # Max requests per minute
  attributes:
    layer: 'presentation'            # domain | application | presentation | infrastructure | core | config
    tags: ['students', 'academics', 'controllers', 'blade']

# File-level declarations for items residing within this directory
files:
  StudentController.php:
    description: 'Handles student enrollment, profile editing, and report card generation'
    usage: 'Registered in src/School/etc/routes.yml'
    stability: 'stable'
    author: 'Lead Developer'
    review_date: '2027-01-01'
    checksum: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
    signature: '7f83b1657ff1fc53b92dc18148a1d65dfc2d4b1fa3d677284addd200126d9069'
    security:
      roles: ['admin', 'teacher']
      allow_network: true
      allow_filesystem: true
      allow_env: false
      allow_shell: false
    attributes:
      implements: ['SPP\Core\Interfaces\RoutableInterface']
      layer: 'presentation'
      tags: ['controller', 'students', 'enrollment']
      slo_budget:
        max_duration_ms: 250
        max_memory_mb: 32
        max_db_queries: 15
      telemetry:
        sample_rate: 1.0
        trace_context: true
      compliance:
        data_classification: 'RESTRICTED'  # PUBLIC | INTERNAL | RESTRICTED | PII_CONFIDENTIAL
        gdpr_relevant: true

# Subdirectory references
directories:
  partials:
    description: 'Blade partial view fragments for student UI cards and tables'
    usage: 'Referenced via @spppartial("partials/name.html")'
```

---

## 3. Capability Security Model (`ManifestSecurityGuard`)

SPP enforces fine-grained capability isolation based on manifest declarations:

1. **`allow_network`**:
   - `false`: The file or directory is forbidden from making outbound HTTP, socket, cURL, or DNS requests.
   - `true`: Outbound network access is permitted.
   - `array`: Outbound connections are restricted strictly to the declared whitelist of hosts, IPs, or CIDR blocks (e.g., `['api.stripe.com', '10.0.0.0/8']`).
2. **`allow_filesystem`**:
   - `false`: Blocks arbitrary filesystem mutations.
   - `true`: Standard read/write permissions within workspace boundaries.
   - `array`: Whitelist of relative directory subtrees the code is permitted to read or mutate.
3. **`allow_env`**:
   - Guards access to sensitive environment variables (`getenv()`, `$_ENV`).
4. **`allow_shell`**:
   - Controls execution of CLI subshells (`exec()`, `shell_exec()`, `system()`, `proc_open()`). High-risk by default (`false`).
5. **Hierarchical Inheritance**:
   - If a file entry does not declare a security block, it inherits the policy declared in `directory.security`.
   - If `directory.security` is omitted, parent directory manifests are inspected upward to the workspace root.

---

## 4. Cryptographic Integrity & Signing Engine (`FileIntegritySigner`)

Manifest OS provides built-in defense against file tampering, unauthorized modifications, and supply-chain corruption:

### 1. SHA-256 Checksums
Every tracked file records its SHA-256 digest under `files.<filename>.checksum`. During health scans or manual verification (`StorageAdminService::verifyFileIntegrity()`):
- The file on disk is hashed using streaming SHA-256.
- If the hash differs from the manifest, the status is marked **`tampered`** and a critical diagnostic alert is generated.

### 2. HMAC Digital Signatures
For hardened zero-trust environments, files can be cryptographically signed using a cluster secret key:
$$\text{Signature} = \text{HMAC-SHA256}(\text{File Contents}, \text{Secret Key})$$
When a key is supplied, `FileIntegritySigner` verifies both the SHA-256 checksum and the HMAC signature. Any alteration to the file or the manifest signature triggers an `invalid_signature` alert.

---

## 5. Automated Self-Healing & AST Synchronization (`props:heal`)

Developers do not need to author manifests by hand. The native AST engine automatically reconciles code changes:

- **Class Contracts & Interfaces**: Parses class tokens to extract `implements <Interface>` and backfills `attributes.implements`.
- **Architectural Layers**: Inspects class inheritance (`SPPEntity` -> `layer: domain`, `ViewController` -> `layer: presentation`).
- **Template Variables**: Scans Blade/Twig templates to discover referenced props and registers them under `attributes.required_props`.
- **Orphan Pruning**: Deleting a file from disk automatically cleans its entry during directory healing.
- **Checksum Calculation**: Untracked files discovered on disk are immediately hashed and registered.

---

## 6. High-Performance Compilation & SQLite Catalog (`props:compile`)

While YAML manifests are human-readable and version-controlled, reading thousands of YAML files on every web request would introduce I/O latency. Manifest OS solves this with a **Two-Tier Compilation Architecture**:

1. **Tier 1 — Compiled PHP Array Cache (`var/cache/manifests_catalog.cache`)**:
   - Serialized, pre-parsed memory-mappable array containing all workspace properties.
   - Loads in under $1\,\text{ms}$ during application boot.
2. **Tier 2 — SQLite WAL Ledger Index (`var/cache/manifest_catalog.db`)**:
   - Structured SQLite database indexed on `path`, `filename`, `layer`, `tags`, `implements`, and `author`.
   - Enables blazing-fast $O(\log N)$ search queries (`php spp.php props:search`) and full-text pattern matching across millions of codebase attributes.
