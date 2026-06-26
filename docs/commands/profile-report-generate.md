# NAME
`profile:report:generate` - Dump a performance profile trace for debugging

# SYNOPSIS
`php spp.php profile:report:generate`

# PURPOSE
The `profile:report:generate` command is a straightforward debugging tool intended to trigger the generation and dumping of a performance profile trace to the filesystem. It is useful when developers need a concrete, snapshot view of profiling telemetry (though its current implementation serves primarily as a stub or placeholder for future enhancements).

# OPTIONS AVAILABLE
This command does not accept any specific options or arguments.

# UNDER THE HOOD ACTIVITY
When the command is executed, it first outputs a status message to standard output indicating that the trace generation is beginning. 

Under the hood, it constructs an absolute file path for the output using the `SPP_BASE_DIR` constant, appending the string `/tmp/profile_` followed by the current UNIX timestamp (generated via PHP's `time()` function) and a `.json` extension. It then uses `file_put_contents()` to write a hardcoded, serialized JSON payload (`{"status":"ok","trace":[]}`) directly into this generated path. Finally, it echoes the path of the generated file back to the console so the user can locate it. 

*(Note: In the current iteration of the framework, this command generates a stubbed empty trace array. The actual integration with the SPPProfile module for live trace dumping appears to be slated for future development or is handled externally.)*

# EXAMPLES
Generate a performance profile trace:
```bash
php spp.php profile:report:generate
```
Expected output:
```
Generating performance profile trace...
Report generated at: /path/to/project/tmp/profile_1690000000.json
```
