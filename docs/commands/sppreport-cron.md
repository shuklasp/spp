# NAME

sppreport:cron - Trigger SPP Report threshold alerts and scheduled jobs

# SYNOPSIS

`php spp.php sppreport:cron`

# PURPOSE

The `sppreport:cron` command manually triggers the internal reporting engine of the SPP Framework. It is designed to evaluate data thresholds, compile scheduled analytics, and dispatch alert notifications. While it can be run manually by a developer for testing, it is primarily intended to be executed periodically by a system task scheduler (like Linux `cron`).

# OPTIONS AVAILABLE

This command accepts no arguments or flags.

# UNDER THE HOOD ACTIVITY

The CLI command itself acts as a lightweight wrapper to invoke a separate procedural cron script. 

When executed, it resolves the absolute path to the core execution script located at `dirname(__DIR__) . '/sppreport_cron.php'` relative to the command's own directory structure. It verifies that this target file exists on the filesystem. If the file is missing, the command outputs a fatal error detailing the expected path and immediately terminates with a non-zero exit code (`exit(1)`).

If the script exists, it is loaded into the current runtime memory using PHP's `require` construct. The `sppreport_cron.php` script assumes control of the execution flow. Typically, this background script connects to the application database, scans configured reporting metrics against predefined threshold rules, generates necessary report payloads, and utilizes the framework's mailer or notification services to send alerts to administrators.

Once the required script finishes executing its synchronous logic, control returns to the CLI command, which prints `"Cron execution completed successfully."` to standard output.

# EXAMPLES

**Manually trigger the report cron jobs:**
```bash
php spp.php sppreport:cron
```

**Typical crontab entry to run this command every hour:**
```bash
0 * * * * cd /var/www/school1 && php spp.php sppreport:cron >> /var/log/sppreport.log 2>&1
```
