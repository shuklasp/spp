# NAME
**deploy:build** - Create a local deployment artifact bundle without pushing

# SYNOPSIS
`php spp.php deploy:build <target_uri> [--key=YOUR_API_KEY] [--no-db] [--no-files]`

# PURPOSE
Calculates the delta (diff) between the local environment and a remote target server, then builds a compressed ZIP artifact containing only the updated files and necessary database schema modifications. The artifact is saved locally and can be deployed at a later time.

# OPTIONS AVAILABLE
- `<target_uri>` : **Required.** The URI of the target environment to compare against.
- `--key=<api_key>` : **Optional.** API key to authenticate with the remote node.
- `--no-db` : **Optional.** Skips the database schema comparison.
- `--no-files` : **Optional.** Skips the file system structure comparison.

# UNDER THE HOOD ACTIVITY
The command initializes a `TargetConnection` to communicate with the remote server. If `--no-files` is absent, it uses `\SPPMod\SPPDeploy\Scanner\ProjectScanner` to hash all local files within `SPP_BASE_DIR`. If `--no-db` is absent, `\SPPMod\SPPDeploy\Scanner\DbScanner` runs to capture the local schema state. These hashes are sent to the target via the `getDiff()` API call. The remote server calculates the difference and returns arrays of files and tables to create, update, or delete. If changes exist, the command provisions a local artifact directory (`var/builds`) and initiates a `\ZipArchive`. It iterates over the 'create' and 'update' file arrays and adds them to the ZIP. For database changes, it checks the local PDO driver (MySQL or SQLite), runs the native `SHOW CREATE TABLE` (or equivalent), and bundles the statements into a `db_snapshot.sql` file within the ZIP. Finally, it creates a JSON manifest of the diff operations alongside the ZIP artifact.

# EXAMPLES
Build an artifact for the production environment:
`php spp.php deploy:build https://api.example.com --key=secret`

Build an artifact excluding database changes:
`php spp.php deploy:build https://api.example.com --no-db`
