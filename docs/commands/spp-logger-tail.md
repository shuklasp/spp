# NAME

`spp logger:tail` - Tail the SPP application log file

# SYNOPSIS

`php spp.php logger:tail`

# PURPOSE

The `logger:tail` command provides a quick, OS-independent snapshot of the most recent entries in the main SPP application log. It is useful in environments where native tools like `tail` might not be immediately available (like some Windows shells).

# OPTIONS AVAILABLE

- `--lines=<int>`: Number of trailing log lines to output (minimum 1, maximum 500, default: 20). May also be specified as the first positional argument (e.g. `php spp.php logger:tail 50`).

# UNDER THE HOOD ACTIVITY

`LoggerTailCommand` isolates the primary log file path at `SPP_LOG_DIR/spp.log`. It verifies the file exists, alerting the user if it does not.
The command parses the `--lines` option or positional argument, safely clamped between 1 and 500 lines (defaulting to 20). It loads lines from `spp.log` and extracts the trailing slice using `array_slice($lines, -$lineCount)`. These elements are printed to standard output.
Note: The command strictly issues a notice that it produces a static snapshot, advising the user to use `tail -f` for native real-time monitoring.

# EXAMPLES

**View the last 20 lines of the application log (default):**
```bash
php spp.php logger:tail
```

**View the last 50 lines of the application log:**
```bash
php spp.php logger:tail --lines=50
```
