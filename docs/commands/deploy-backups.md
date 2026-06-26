# NAME
**deploy:backups** - List available snapshot backups on a remote target for rollback

# SYNOPSIS
`php spp.php deploy:backups <target_uri> [--key=YOUR_API_KEY]`

# PURPOSE
Queries a remote deployment node to list all available rollback snapshots, allowing administrators to review backup histories before initiating a restoration.

# OPTIONS AVAILABLE
- `<target_uri>` : **Required.** The HTTP URI of the target server to query.
- `--key=<api_key>` : **Optional.** The API authentication key to authorize the request on the remote node. Defaults to `default_cli_key`.

# UNDER THE HOOD ACTIVITY
The command extracts the target URI and the optional `--key` flag from the runtime arguments. It calls `\SPPMod\Sppdeploy\Deployer\TargetConnection::resolve($target, $apiKey)` to instantiate a client for the remote node. Using this connection, it invokes the `getBackups()` method, which performs an HTTP request to the target server. The target responds with a JSON array of backup metadata. The command checks the `status` flag of the response. If successful, it parses the `backups` array and formats a tabular view containing the backup date, filename (snapshot ID), and physical file size rounded to MB.

# EXAMPLES
Check backups on the staging server:
`php spp.php deploy:backups https://staging.example.com --key=secret_123`
