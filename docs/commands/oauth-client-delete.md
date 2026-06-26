# NAME
**oauth:client:delete** - Delete an OAuth 2.0 Client App

# SYNOPSIS
`php spp.php oauth:client:delete <id>`

# PURPOSE
Permanently removes a specific OAuth 2.0 Client Application from the system, effectively revoking all access and active sessions bound to this client.

# OPTIONS AVAILABLE
- `<id>` : **Required.** The unique Client ID of the OAuth application to be deleted (e.g., `client_a1b2c3d4`).

# UNDER THE HOOD ACTIVITY
The command accepts the Client ID and interfaces with the database via `SPPDB`. It executes a `DELETE` query against the `oauth_clients` table targeting the provided ID. To ensure cascading data integrity and immediate revocation of access, it also performs localized cleanup by deleting all associated token records from the `oauth_tokens` table and any pending authorization codes from the `oauth_auth_codes` table. A success message is output confirming the client and its tokens have been purged.

# EXAMPLES
Delete an obsolete OAuth client:
`php spp.php oauth:client:delete client_8f7b23aa`
