## `logger:tail`

**Description**: Tail the SPP application log file

### Synopsis
```bash
php spp.php logger:tail [--lines=20]
```

### Options
- `--lines=<int>`: Number of trailing log lines to retrieve (1 to 500, default: 20). Can also be supplied as the first positional argument.

### Under the Hood
Based on static analysis of the command's source code:
- Verifies existence of `SPP_LOG_DIR/spp.log`.
- Parses `--lines` option or positional argument, safely bounded between 1 and 500 lines.
- Reads lines into memory and displays the bounded trailing slice.

