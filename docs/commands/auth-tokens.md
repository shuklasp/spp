# NAME
**auth:tokens** - Manage Personal Access Tokens for API Authentication

# SYNOPSIS
`php spp.php auth:tokens <action> [args]`

# PURPOSE
Allows administrators to generate, revoke, and list Personal Access Tokens used for API authentication on behalf of specific users.

# OPTIONS AVAILABLE
- `generate <userid> ["Token Name"]` : Generates a new API token for the specified user. The token name is optional and defaults to "CLI Generated Key".
- `revoke <token_id>` : Revokes and deletes a specific token by its ID.
- `list [userid]` : Lists all tokens in the system. If `userid` is provided, filters the list to only that user's tokens.

# UNDER THE HOOD ACTIVITY
The command delegates to three internal sub-routines based on the action argument:
- **generate**: Resolves the user via `SPPUser`. If valid, it utilizes `\SPPMod\SPPAuth\TokenGuard::createToken()` to forge a new token record. It outputs the raw cryptographic token to the console exactly once, warning the user to copy it.
- **revoke**: Executes a direct SQL `DELETE` query via `SPPDB` against the `personal_access_tokens` table, destroying the token by its primary key.
- **list**: Runs a `SELECT` query against the `personal_access_tokens` table, joining on the user ID if specified. It calculates if the token is active or expired based on the `expires_at` column, and renders the result as an ASCII table using the global `printTable` utility.

# EXAMPLES
List all system tokens:
`php spp.php auth:tokens list`

Generate a token for user ID 12 with a custom name:
`php spp.php auth:tokens generate 12 "Mobile App Integration"`

Revoke token ID 45:
`php spp.php auth:tokens revoke 45`
