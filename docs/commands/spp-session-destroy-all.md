# NAME

`session:destroy-all` - Invalidate all active sessions across the application.

# SYNOPSIS

`php spp session:destroy-all`

# PURPOSE

The `session:destroy-all` command forcefully invalidates all active sessions in the application. This behaves as an emergency or administrative reset, logging out all connected users unconditionally.

# OPTIONS AVAILABLE

This command accepts no arguments or options.

# UNDER THE HOOD ACTIVITY

When executed, the command issues a warning message to the console noting the dangerous nature of the action. It operates as follows:
1. **Path Retrieval**: It identifies the directory where PHP stores session files using `session_save_path()`. If this yields no directory, it defaults to the system's temporary directory (`sys_get_temp_dir()`).
2. **File Discovery**: It scans the target directory using `glob()` to locate all files prefixed with `sess_*`.
3. **Indiscriminate Deletion**: Iterating through the matched files, it immediately deletes (unlinks) every valid session file it encounters, without checking its creation date, modified date, or validity.
4. **Summary Output**: After deleting all matching files, it outputs a summary indicating the total number of sessions destroyed.

# EXAMPLES

**Invalidate all sessions and force all users to log out:**
```bash
php spp session:destroy-all
```
*(Caution: Use this only when absolutely necessary, such as during a critical security audit or major system migration).*
