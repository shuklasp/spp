# NAME

`spp live:status` - Check the status of websocket/polling servers

# SYNOPSIS

`php spp.php live:status [--app=<app_context>]`

# PURPOSE

The `live:status` command is a diagnostic tool to verify whether the SPPLive module—responsible for real-time connection handling and CDC streaming endpoints—is loaded and active within a given application context.

# OPTIONS AVAILABLE

- `--app=<app_context>`: (Optional) Verify the live status within a specific application context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

`LiveStatusCommand` extracts the `--app` option if provided. It utilizes `\SPP\Scheduler::withContext` to initialize the requested application context. 
Inside the closure, it evaluates whether the `\SPPMod\SPPLive\SPPLive` class exists in the PHP execution space. This relies on the framework's module auto-loader having successfully loaded the `spplive` module for that app context. If the class is found, it confirms that the module is active. Otherwise, it reports that SPPLive is inactive.

# EXAMPLES

**Check live status for the default app:**
```bash
php spp.php live:status
```
