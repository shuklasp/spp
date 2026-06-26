# env:status

## NAME
env:status - Display system health and environment status

## SYNOPSIS
`php spp.php env:status [--app=<app_name>]`

## PURPOSE
A comprehensive diagnostic utility yielding immediate performance metrics, connectivity statuses, and global health checks for the active SPP configuration.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Evaluated application space.

## UNDER THE HOOD ACTIVITY
Inside the closure of the bounded context, it dynamically maps server diagnostics using PHP intrinsic functions (`PHP_VERSION`, `PHP_OS`, `ini_get('memory_limit')`). It probes database connectivity by deliberately wrapping a new instantiation of `\SPPMod\SPPDB\SPPDB()` within an output buffer (`ob_start()`) and a `try/catch` block to gracefully fail without polluting the CLI stdout stream. It uses `is_writable()` to verify filesystem permissioning. It parses `session_save_path()` (defaulting to `sys_get_temp_dir()`) and counts valid active web sessions via `glob('sess_*')`. It weighs the size of the global middleware layers from `\SPP\Registry` and reads the queue size. Finally, it scores the integrity metrics on a 100% scale and outputs the matrix.

## EXAMPLES
```bash
php spp.php env:status
```
