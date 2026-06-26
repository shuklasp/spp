# NAME

deploy:run - Securely execute an arbitrary shell command on the remote server

# SYNOPSIS

`php spp.php deploy:run <target_uri> "<command>" [--key=YOUR_API_KEY]`

# PURPOSE

The `deploy:run` command allows developers to execute arbitrary shell commands directly on a remote server from their local terminal, routing the command securely through the deployment API rather than requiring an SSH session. This is exceptionally useful for running framework-specific CLI tasks (like clearing cache or running migrations) on the remote target.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The remote environment URI.
*   `"<command>"` (Required)
    The exact shell command you wish to execute. It should be wrapped in quotes to prevent local shell expansion.
*   `--key=YOUR_API_KEY` (Optional)
    The API token to authenticate the request.

# UNDER THE HOOD ACTIVITY

The command parses the CLI arguments, ensuring that both a target URI and the shell command string are provided. It then initializes the `TargetConnection::resolve()` HTTP handler.

The CLI calls `$conn->runCommand($commandToRun)`. This encapsulates the shell string into a secure API payload and transmits it to the remote deployment receiver. 

On the remote server, the deployment receiver (if configured to allow arbitrary remote execution) utilizes PHP's system execution functions (such as `exec()` or `proc_open()`) to execute the provided string within the remote machine's shell context. It captures the standard output, standard error, and the exit code of the process.

The remote server packages this data and sends it back to the local CLI. The CLI then prints the standard output verbatim to the terminal. Finally, it evaluates the returned `exit_code`. If the exit code is not `0` (indicating an error at the OS level), it outputs a warning highlighting the non-zero exit code. Otherwise, it prints a success confirmation.

# EXAMPLES

**Clear the cache on the remote production server:**
```bash
php spp.php deploy:run http://prod.example.com "php spp.php cache:clear" --key=my_key
```

**Check the disk space on the remote server:**
```bash
php spp.php deploy:run http://prod.example.com "df -h"
```
