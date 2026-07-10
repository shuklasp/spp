## `tab`

**Purpose**: Manage interactive multi-tab sessions within the SPP Shell environment.

### Synopsis
```bash
tab [ACTION] [OPTIONS]
```

### Extended Usage
```text
The 'tab' command is a high-productivity built-in utility available exclusively within the SPP interactive shell (launched via 'php spp.php shell'). It provides virtual workspaces (tabs) allowing developers to manage multiple concurrent tasks, switch between different application contexts, run background tasks, and receive inter-process communication (IPC) notifications across tabs.

Available Actions:
  tab list           List all currently open tabs, their active application contexts, and status.
  tab new [app]      Create and switch to a new tab, optionally binding it to a specific application context.
  tab switch <id>    Switch the active viewport to the specified tab ID.
  tab close <id>     Close the specified tab. If the active tab is closed, the shell switches to Tab 1.
```

### Options Available
- `list` : Display a summary of all active tabs and background job counts.
- `new` : Spawn a fresh tab workspace.
- `switch` : Navigate to a different open tab.
- `close` : Terminate a tab session and clean up associated background tracking.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Manages in-memory session arrays for virtual tab contexts.
- Facilitates cross-tab Inter-Process Communication (IPC) via `var/ipc/tab_notifications.json`.
- Integrates cleanly with readline history buffers to isolate command logs per session.
