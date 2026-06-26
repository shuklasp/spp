# db:verify

## NAME
db:verify - Runs the SPP XDB MySQL Compatibility Verification Suite

## SYNOPSIS
`php spp.php db:verify`

## PURPOSE
Executes the SPP XDB MySQL compatibility check script to ensure the SPPDB abstraction layers handle cross-engine translations without syntax faults or connection errors.

## OPTIONS AVAILABLE
This command does not accept additional options.

## UNDER THE HOOD ACTIVITY
When triggered, the command looks for a specific test suite file located at `spp/modules/spp/sppxdb/test_mysql_compatibility.php`. If the file exists, it uses the native PHP `passthru()` function to spawn a sub-process running the script directly. This offloads the heavy functional testing directly to the test harness and relays the real-time stdout feedback to the user's terminal.

## EXAMPLES
```bash
php spp.php db:verify
```
