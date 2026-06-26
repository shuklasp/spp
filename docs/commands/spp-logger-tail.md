# NAME

`spp logger:tail` - Tail the SPP application log file

# SYNOPSIS

`php spp.php logger:tail`

# PURPOSE

The `logger:tail` command provides a quick, OS-independent snapshot of the most recent entries in the main SPP application log. It is useful in environments where native tools like `tail` might not be immediately available (like some Windows shells).

# OPTIONS AVAILABLE

This command accepts no additional arguments.

# UNDER THE HOOD ACTIVITY

`LoggerTailCommand` isolates the primary log file path at `SPP_LOG_DIR/spp.log`. It verifies the file exists, alerting the user if it does not.
The command is intentionally simplified and static: it uses PHP's `file()` function to load the entire log file into an array of strings in memory. It then extracts the last 20 elements of the array using `array_slice($lines, -20)`. These elements are printed to standard output. 
Note: The command strictly issues a warning that it produces a static snapshot, advising the user to use `tail -f` for native real-time monitoring.

# EXAMPLES

**View the last 20 lines of the application log:**
```bash
php spp.php logger:tail
```
