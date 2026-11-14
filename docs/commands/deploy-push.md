## deploy:push

**Purpose**: Push local application code, framework updates, and database schema migrations to a remote SPP deployment target via differential state synchronization with real-time 6-stage telemetry progress indicators.

### Synopsis

```bash
php spp.php deploy:push [target] [options]
```

### Extended Usage

The `deploy:push` command is the core client-side deployment driver in the SPPDeploy subsystem. It provides atomic, differential live synchronization between the local workspace and a remote SPP target server over authenticated HTTPS endpoints.

Instead of re-uploading the entire codebase or executing heavy file-by-file transfers, `deploy:push` evaluates cryptographic file hashes and database state vectors, packages only the modified or missing artifacts into a lightweight, encrypted deployment archive, and transfers it to the target server's receiver gateway.

During deployment execution, the command outputs real-time, non-blocking telemetry indicators divided into 6 distinct operational stages:
1. `[1/6] 🩺 Checking remote target health & connectivity`: Verifies network reachability, target PHP runtime, required PHP extensions (e.g., `ext-zip`), and database connectivity.
2. `[2/6] 🔍 Scanning local workspace files`: Traverses local files and computes cryptographic checksums while honoring exclusion boundaries.
3. `[3/6] 📡 Computing remote differential signatures`: Contacts the remote server's differential endpoint to determine the exact delta (+create, ~update, -delete).
4. `[4/6] 📦 Packaging differential archive payload`: Packages only changed files into a compressed deployment archive.
5. `[5/6] 🚀 Transmitting deployment payload`: Uploads the compressed payload to the remote receiver with transfer byte counters and speed metrics.
6. `[6/6] ⚙️ Applying server updates, backups & executing post-deploy hooks`: Server extracts changes, creates automated safety backups, synchronizes SQLite/MySQL schemas, and executes post-deployment hooks.

#### Common Examples:

```bash
# Push all differential changes to the default target
php spp.php deploy:push

# Push differential updates to a specific named target
php spp.php deploy:push production

# Deploy only framework core updates (spp/, public/, etc/) to framework directory
php spp.php deploy:push production --framework-only

# Deploy a specific domain and its domain apps to its domain directory
php spp.php deploy:push production --domain=example.com

# Deploy a specific global app (or domain app)
php spp.php deploy:push production --app=school1

# Dry-run inspection without transmitting payload
php spp.php deploy:push production --dry-run

# Deploy only filesystem changes, skipping database migrations
php spp.php deploy:push production --no-db

# Deploy only database schema evolutions, skipping file synchronization
php spp.php deploy:push production --no-files

# Force push even if safety health warnings or version mismatches exist
php spp.php deploy:push production --force
```

### Options Available

- `target`: Named target defined in `.sppdeploy.yml` (e.g., `production`, `staging`). Defaults to default target.
- `--domain=<domain>`: Scope deployment to a specific domain directory (e.g. `domains/example.com`).
- `--domains=<d1,d2>`: Comma-separated list of domains to deploy.
- `--app=<name>`: Scope deployment to a specific application (resolves both global apps in `src/` and domain apps in `domains/<domain>/src/`).
- `--apps=<a1,a2>`: Comma-separated list of applications to deploy.
- `--global-apps`: Scope deployment to all global apps residing in `src/` and `apps/`.
- `--framework-only`: Deploy strictly the framework core (`spp/`, `public/`, `etc/`, CLI, and root entrypoints) to the framework directory.
- `--no-framework`: Exclude framework core files when deploying scoped apps or domains.
- `--key=<token>`: Deploy API authentication key or secret token override.
- `--artifact=<path>`: Custom deployment payload output path instead of default temporary directory.
- `--dry-run`: Calculate and display the exact file and database diff without modifying the target server.
- `--no-db`: Skip database schema comparison and migrations during deployment.
- `--no-files`: Skip filesystem diff and file uploads, executing only database migrations and post-deploy hooks.
- `--exclude-files=<p1,p2>`: Comma-separated list of filenames or glob patterns to exclude from deployment push (e.g. `*.local.yml,config.local.php,data/*.sqlite`). Also respects `.sppignore` and `exclude` in `.sppdeploy.yml`.
- `--exclude-data`, `--no-data`: Exclude all SQLite database files (`*.sqlite`, `*.sqlite3`, `*.db`) and database schema definitions from live push.
- `--exclude-tables=<t1,t2>`: Comma-separated list of database table patterns to exclude from remote synchronization (e.g. `test_*,temp_*`).
- `--silent`: Suppress verbose deployment output, progress step badges, and ASCII banners, emitting only a concise 1-line status summary.
- `--quiet`, `-q`: Completely suppress all output except critical error messages (exit code 0 on success).
- `--force`: Bypass non-critical pre-flight validation warnings and proceed with deployment.

### Under the Hood Activity

1. **Target Resolution & Health Check**: Parses `.sppdeploy.yml` for target endpoint and authentication secrets, dispatching a ping payload to verify PHP version, `ZipArchive` availability, and DB health.
2. **Distributed Mutex Locking**: Acquires a distributed deployment lock on the target server to prevent concurrent deployment race conditions.
3. **Workspace File & DB Hashing**: Employs `\SPPMod\SPPDeploy\Scanner\ProjectScanner` and `DbScanner` to generate local SHA-256 state vectors.
4. **Multi-Directory Topology Routing**: `Receiver::resolveDestinationPaths` automatically evaluates incoming entries:
   - Framework files are extracted to the framework directory (`/var/www/spp`).
   - Domain app files are extracted to the framework domain directory AND the domain's designated virtual host webroot (e.g. `/var/www/<domain>/` or `/home/<user>/...`).
   - Global apps are routed to the framework directory or designated `global_apps_dir` / `apps_dir`.
5. **Differential Handshake**: Submits local hashes to `/spp/deploy/receiver.php?action=diff` and receives remote differential instructions.
6. **Payload Compilation**: Compiles changed assets into an in-memory or temporary `.zip` archive via `\ZipArchive`.
7. **Payload Transmission & Execution**: Streams the payload via HTTP POST to the target receiver, where `Receiver::handleDeploy()` extracts files to their resolved destinations, creates rolling backups, applies migrations, and executes registered hooks.
8. **Lock Release**: Automatically releases the distributed deployment mutex in a `finally` block upon completion or failure.
