# NAME

deploy:logs - View and tail remote application error logs securely over HTTP

# SYNOPSIS

`php spp.php deploy:logs <target_uri> [--key=YOUR_API_KEY] [--tail] [--lines=100]`

# PURPOSE

The `deploy:logs` command allows developers to remotely inspect the application log files of a target environment without requiring direct file system or SSH access. It supports fetching a specific number of recent log lines and optionally "tailing" the log in real-time by polling the remote server for new entries.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The connection URI or identifier of the remote environment.
*   `--key=YOUR_API_KEY` (Optional)
    The authentication token required to access the remote server's logs. Defaults to `default_cli_key`.
*   `--tail` (Optional)
    If specified, the command will continuously poll the remote server for new log entries, similar to the Unix `tail -f` command.
*   `--lines=100` (Optional)
    The number of historical log lines to fetch during the initial request. Defaults to 100.

# UNDER THE HOOD ACTIVITY

The command parses the CLI arguments, extracting the target URI, API key, the `--tail` flag, and the number of lines to request. It then establishes a connection handler via `TargetConnection::resolve()`.

It performs an initial HTTP request to the remote server by invoking `$conn->getLogs(-1, $lines)`. The remote system is expected to read the application log file from the end, returning the last `$lines` number of lines. The response includes the contents of the log file, the absolute file path on the remote system, and an `offset` integer indicating the current byte position at the end of the file.

The command outputs the file path and the retrieved log lines to the console. If the `--tail` flag is not provided, execution terminates here.

If `--tail` is active, the command enters an infinite `while (true)` loop. In each iteration, it pauses execution for 2 seconds (`sleep(2)`) to avoid overwhelming the network and server. It then calls `$conn->getLogs($offset, 0)`, sending the previously recorded byte offset back to the server. The remote server seeks to that specific byte offset in the log file, reads any new data appended since the last request, and returns the new chunk along with an updated offset. The command then echoes the new lines to the console and updates its local offset state, creating a seamless real-time stream until interrupted by the user (`Ctrl+C`).

# EXAMPLES

**Fetch the last 50 lines of logs from a remote server:**
```bash
php spp.php deploy:logs http://prod.example.com --key=secret_key --lines=50
```

**Tail the remote application logs continuously:**
```bash
php spp.php deploy:logs http://staging.example.com --tail
```
