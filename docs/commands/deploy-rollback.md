## `deploy:rollback`

**Purpose**: Roll back a remote target to a specific snapshot backup ID

### Synopsis
```bash
php spp.php deploy:rollback [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:rollback [target_uri] <backup_id> [--key=YOUR_API_KEY] [--force]

```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--force` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).

