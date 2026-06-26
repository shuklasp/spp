# NAME

`spp logger:clear` - Clear the SPP application logs

# SYNOPSIS

`php spp.php logger:clear`

# PURPOSE

The `logger:clear` command is a maintenance utility designed to rapidly flush all log files managed by the SPP framework, typically used during development or after resolving a major incident to regain disk space and reset log state.

# OPTIONS AVAILABLE

This command accepts no additional arguments.

# UNDER THE HOOD ACTIVITY

The `LoggerClearCommand` targets the system's log directory as defined by the constant `SPP_LOG_DIR`. It utilizes the PHP `glob()` function with the pattern `SPP_LOG_DIR/*.log` to find all log files within the directory.
For each file matched, the command truncates it by calling `file_put_contents($file, "")`. This empties the file without changing its permissions or ownership, preventing potential access errors that might occur if the files were deleted and recreated. A count of cleared files is tracked and output upon completion.

# EXAMPLES

**Clear all framework logs:**
```bash
php spp.php logger:clear
```
