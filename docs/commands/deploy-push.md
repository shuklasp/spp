# NAME

deploy:push - Push the local project state to a remote SPP target server

# SYNOPSIS

`php spp.php deploy:push <target_uri> [--key=YOUR_API_KEY] [--dry-run] [--no-db] [--no-files] [-y|--force] [--artifact=PATH]`

# PURPOSE

The `deploy:push` command is the core deployment engine of the SPP Framework. It synchronizes your local application code and database schema with a remote server. It features an intelligent delta-diffing system, secure chunked uploads, remote health checks, and pre/post-deployment hook execution.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The destination remote environment URI.
*   `--key=YOUR_API_KEY` (Optional)
    Authentication token. Defaults to the `SPP_DEPLOY_TOKEN` environment variable.
*   `--dry-run` (Optional)
    Performs all diffing and checks but skips actual transmission and deployment.
*   `--no-db` (Optional)
    Skips scanning and pushing database schema changes.
*   `--no-files` (Optional)
    Skips scanning and pushing file system changes.
*   `-y` / `--force` (Optional)
    Skips the interactive confirmation prompt before pushing.
*   `--artifact=PATH` (Optional)
    Instead of dynamically building a payload, push a pre-compiled zip artifact file (and its corresponding `.json` manifest).

# UNDER THE HOOD ACTIVITY

The `deploy:push` pipeline is extensive and follows a strict lifecycle:

1.  **Pre-Deploy Hooks**: It parses the local `.sppdeploy.yml` configuration file. If `pre_deploy` scripts are defined, it executes them locally via `exec()`. If any script fails (returns a non-zero exit code), the deployment immediately aborts.
2.  **Artifact Mode**: If `--artifact` is provided, the command bypasses scanning. It loads the compiled `.zip` and `.json` manifest directly from disk and jumps to the transmission phase.
3.  **State Scanning**: If not using an artifact, `ProjectScanner` and `DbScanner` are initialized to hash local files and database tables.
4.  **Health & Environment Checks**: It pings the remote server (`$conn->getHealth()`) to ensure the remote `zip` extension is loaded and the `spp/var` directory is writable. It also fetches the remote `.env` keys, comparing them against the local environment variables. Any keys present locally but missing remotely will trigger a console warning.
5.  **Delta Diffing**: Local hashes are sent to the remote server to compute the delta diff (`create`, `update`, `delete` arrays for both files and db). If no changes exist, it exits.
6.  **Confirmation**: Unless `--force` or `--dry-run` is active, it presents a summary of operations and pauses for user confirmation via `STDIN`.
7.  **Payload Generation**: It creates a temporary ZIP archive (`var/cache/deploy_payload.zip`). It bundles only the files marked for `create` or `update`. If database changes are required, it generates the specific `DROP` and `CREATE` SQL statements for the affected tables and bundles them as `db_snapshot.sql` inside the ZIP.
8.  **Chunked Transmission**: To handle large deployments over restricted networks (e.g., Cloudflare limits), the CLI splits the zip file into 2MB chunks. It iterates through the chunks, encoding them in base64, and uploading them sequentially via `$conn->uploadChunk()`. A unique `sessionId` tracks the upload state on the server.
9.  **Finalization**: On the final chunk upload, the server reconstructs the ZIP, applies the file changes, executes the SQL snapshot, fires any defined remote webhooks, and returns a final success response containing webhook statuses.

# EXAMPLES

**Standard push to production:**
```bash
php spp.php deploy:push http://prod.example.com --key=my_secure_token
```

**Push a pre-built artifact archive forcefully:**
```bash
php spp.php deploy:push http://staging.example.com --artifact=builds/release-v1.zip -y
```
