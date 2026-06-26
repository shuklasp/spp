# NAME

`session:clean` - Clean up expired sessions.

# SYNOPSIS

`php spp session:clean`

# PURPOSE

The `session:clean` command performs garbage collection on active user sessions. It identifies and permanently deletes session files that have exceeded the system's maximum allowed lifetime.

# OPTIONS AVAILABLE

This command accepts no arguments or options.

# UNDER THE HOOD ACTIVITY

The command executes a standard session garbage collection routine manually:
1. **Path Retrieval**: It determines the current session storage directory by calling PHP's `session_save_path()`. If this value is empty or unset, it falls back to the system's temporary directory (`sys_get_temp_dir()`).
2. **Lifetime Lookup**: It queries the PHP configuration (`ini_get('session.gc_maxlifetime')`) to determine the threshold for session expiration (in seconds).
3. **File Discovery**: It scans the session directory for files matching the pattern `sess_*`.
4. **Validation and Cleanup**: For each discovered session file, it compares the file's last modified time (`filemtime`) against the current timestamp (`time()`). If the difference exceeds the max lifetime, the session is deemed expired and the file is permanently deleted using `unlink()`.
5. **Reporting**: Outputs the directory checked, the lifetime threshold, and the total count of successfully cleaned session files.

# EXAMPLES

**Run the session cleanup:**
```bash
php spp session:clean
```

*This command is highly suitable for being triggered periodically by `schedule:run` or a direct cron job to keep the session storage lightweight.*
