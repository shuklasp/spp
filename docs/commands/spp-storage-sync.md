# NAME

`storage:sync` - Sync local storage with external disks (stub).

# SYNOPSIS

`php spp storage:sync [--app=<appname>]`

# PURPOSE

The `storage:sync` command is intended to synchronize local filesystem assets with configured external storage disks (like AWS S3 or FTP). **Note**: At present, this command is merely a stub and performs no real external data synchronization.

# OPTIONS AVAILABLE

- `--app=<appname>` (Optional): Specify the application namespace. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

1. **Argument Parsing**: Identifies the `--app=` argument from the CLI input.
2. **Context Setup**: Wraps its execution within `\SPP\Scheduler::withContext()`.
3. **Execution Stub**: Outputs placeholder messages stating: "Currently only local disk is configured. No external sync required."

# EXAMPLES

**Invoke the storage sync command:**
```bash
php spp storage:sync
```
