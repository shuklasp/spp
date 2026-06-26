# NAME

`api:key:revoke`

# SYNOPSIS

`php spp.php api:key:revoke --token=<token>`

# PURPOSE

Revoke an existing API token to instantly prevent further client authentication using that key.

# OPTIONS AVAILABLE

- `--token=<token>` : (Required) The literal API token string that should be revoked.

# UNDER THE HOOD ACTIVITY

The command explicitly iterates through the CLI `$args` array to locate the `--token=` parameter and extracts the trailing substring value. If the parameter is missing, it outputs usage instructions to standard output and returns immediately. Upon successfully identifying the token, it prints a success message. 

*Note: In the current iteration of the framework, the actual database status revocation logic is stubbed out. It does not actively perform a SQL `UPDATE` or `DELETE` against the `api_keys` table.*

# EXAMPLES

Revoke a specific API token:
```bash
php spp.php api:key:revoke --token=a1b2c3d4e5f6g7h8...
```
