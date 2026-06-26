# NAME

`audit:lineage`

# SYNOPSIS

`php spp.php audit:lineage [--app=<appname>]`

# PURPOSE

Traverses and verifies the cryptographic Merkle-DAG trace logs, ensuring the immutability and integrity of tracked system state transactions.

# OPTIONS AVAILABLE

- `--app=<appname>` : Target a specific application's lineage log rather than the global default log.

# UNDER THE HOOD ACTIVITY

By default, the command establishes its verification target against the global state log situated at `SPP_APP_DIR . '/var/logs/merkle_lineage.log'`. It scans the CLI arguments for the presence of the `--app=` parameter. If detected, it mutates the target path to isolate the specific application's log directory: `SPP_APP_DIR . '/src/' . $appName . '/var/logs/merkle_lineage.log'`.

The command uses `file_exists()` to ensure the log target is physically present on the disk. If no log exists, it alerts the user that no immutable state transactions have been recorded. 

If the log exists, the command utilizes the native PHP `file()` function, passing `FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES` to efficiently load the entire log sequence into an array while bypassing structural whitespace. It counts the total number of lines (which ostensibly map to continuous cryptographic DAG state signatures) and echoes the total count back to the console, affirming that the "mathematical Merkle root hash sequence is uncompromised." 

*Note: The current implementation performs an optimistic line-count verification rather than executing a full mathematical recalculation of the Merkle root hashes.*

# EXAMPLES

Audit the global state trace:
```bash
php spp.php audit:lineage
```

Audit a specific application's state trace:
```bash
php spp.php audit:lineage --app=financial_ledger
```
