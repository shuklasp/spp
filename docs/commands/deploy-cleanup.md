# NAME
**deploy:cleanup** - Prune old rollback snapshots from the remote target server

# SYNOPSIS
`php spp.php deploy:cleanup <target_uri> [--keep=5] [--key=YOUR_API_KEY]`

# PURPOSE
Requests the remote server to delete old deployment backup snapshots to free up disk space, retaining only a specified number of recent backups.

# OPTIONS AVAILABLE
- `<target_uri>` : **Required.** The URI of the target environment.
- `--keep=<integer>` : **Optional.** The number of recent backup snapshots to retain. Defaults to 5.
- `--key=<api_key>` : **Optional.** API key for remote authentication.

# UNDER THE HOOD ACTIVITY
The command extracts the target URI, the optional API key, and parses the `--keep` argument into an integer. It establishes a remote client instance using `\SPPMod\SPPDeploy\Deployer\TargetConnection::resolve()`. It then invokes the `cleanupBackups($keep)` method, passing the retention integer. This method transmits an HTTP request instructing the remote environment to sort its backup directory and permanently unlink (delete) any archives older than the defined retention threshold. The remote node returns a JSON status payload, which the CLI interprets and displays as a success or failure notification.

# EXAMPLES
Keep only the latest 3 backups on staging:
`php spp.php deploy:cleanup https://staging.example.com --keep=3`
