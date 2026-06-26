# NAME

deploy:plan - Perform a dry run to view file and database changes before deploying

# SYNOPSIS

`php spp.php deploy:plan <target_uri> [--key=YOUR_API_KEY] [--no-db]`

# PURPOSE

The `deploy:plan` command analyzes the local workspace and database against a remote target server to determine what will be changed during a push deployment. It serves as a "dry run" or pre-flight check, computing the exact diffs for files and database schemas without making any actual modifications to the remote environment.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The connection URI of the remote environment to compare against.
*   `--key=YOUR_API_KEY` (Optional)
    The secure API token to authenticate the request with the remote server.
*   `--no-db` (Optional)
    Skips the database schema comparison. If set, only file differences will be calculated and displayed.

# UNDER THE HOOD ACTIVITY

When execution begins, the command instantiates a `FileScanner` to traverse the local project directory (defined by `SPP_BASE_DIR`), generating cryptographic hashes for all tracked files while respecting `.sppignore` rules. If the `--no-db` flag is omitted, it also instantiates a `DbScanner` to inspect the local database schema, generating representations of the current database tables.

The CLI then sends these local state hashes to the remote server via `$conn->getDiff()`. The remote deployment receiver compares the incoming hashes against its own current state, categorizing files and database tables into three arrays: `create`, `update`, and `delete`. The receiver responds with this aggregated `diff` payload.

The command parses this payload and aggregates the total counts. If no changes are detected, it exits cleanly. Otherwise, it prints a structured "PRE-FLIGHT PLAN". It lists the number of files to be created, updated, and explicitly names any files scheduled for deletion.

For database changes, the command dynamically generates the exact raw SQL statements that would be executed on the remote server. It resolves the local PDO driver (`sqlite` or `mysql`) and queries the database engine (e.g., `SHOW CREATE TABLE`) for any tables marked as `create` or `update`. It structures `DROP TABLE IF EXISTS` and `CREATE TABLE` statements, displaying them in the terminal as "PROPOSED SQL STATEMENTS". This provides the developer with full transparency regarding destructive database operations before committing to a push.

# EXAMPLES

**Preview deployment changes to the staging server:**
```bash
php spp.php deploy:plan http://staging.example.com --key=my_secure_token
```

**Preview only file changes, ignoring the database:**
```bash
php spp.php deploy:plan http://prod.example.com --no-db
```
