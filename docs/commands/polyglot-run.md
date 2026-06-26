# NAME
`polyglot:run` - Executes a specific polyglot service directly

# SYNOPSIS
`php spp.php polyglot:run --path=<relative_path_to_service> [args...]`

# PURPOSE
The `polyglot:run` command is utilized to directly invoke a specific polyglot service from the command line, passing down any provided arguments. This is incredibly useful for testing and debugging individual scripts or microservices written in different languages (like Python, Node.js, Go, etc.) without having to trigger them through the main application lifecycle or polyglot bridge interfaces.

# OPTIONS AVAILABLE
* `--path=<relative_path>`
  **Required.** Specifies the relative path (from the base directory) to the polyglot service script that should be executed.
* `[args...]`
  Any additional command-line arguments passed after the path will be securely forwarded directly to the target polyglot script.

# UNDER THE HOOD ACTIVITY
When the command is launched, it iterates through all provided command-line arguments to parse the inputs. It looks for the `--path=` flag to extract the relative file path. It filters out system-level arguments (such as `spp.php`, the command name, and `--app=` flags) and escapes any remaining arguments using `escapeshellarg()` to safely pass them as parameters to the underlying service.

Once the path is determined, the command constructs the full absolute path by prepending `SPP_BASE_DIR` and verifies that the file actually exists on the filesystem. If the file is missing, execution terminates with an error. The command then determines the file extension (e.g., `py`, `js`, `rb`) and uses a hardcoded mapping array to identify the appropriate binary or interpreter (e.g., mapped to `python`, `node`, `ruby`, `bash`, `go run`). If an extension is not registered in the mapping, the process is aborted with an "Unknown interpreter" error.

With the interpreter resolved, the final shell command is composed using `escapeshellcmd()` for the interpreter and script path, appended with the string-joined, escaped service arguments. The constructed command is output to the console for transparency, and then executed synchronously via PHP's `passthru()` function, allowing raw standard output and standard error from the external process to stream directly to the terminal. Finally, it echoes the exit status code returned by the executed process.

# EXAMPLES
Execute a Python script with additional arguments:
```bash
php spp.php polyglot:run --path=services/data_cruncher.py --input=data.csv --verbose
```

Run a Node.js utility:
```bash
php spp.php polyglot:run --path=src/services/mailer.js "recipient@example.com"
```
