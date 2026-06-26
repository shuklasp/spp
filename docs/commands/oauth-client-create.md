# NAME
**oauth:client:create** - Create a new OAuth 2.0 Client App

# SYNOPSIS
`php spp.php oauth:client:create <name> <redirect_uri>`

# PURPOSE
Provisions a new OAuth 2.0 Client Application within the identity provider, generating a secure Client ID and Client Secret pair to be used for authorized API access.

# OPTIONS AVAILABLE
- `<name>` : **Required.** A human-readable name for the OAuth application.
- `<redirect_uri>` : **Required.** The authorized callback URL where the OAuth server will redirect users after successful authentication.

# UNDER THE HOOD ACTIVITY
Upon execution, the command verifies that both `<name>` and `<redirect_uri>` arguments are supplied. It instantiates the `SPPDB` database component to interface with the `oauth_clients` table. It then securely generates a unique `clientId` (prefixed with `client_` followed by 8 random hex characters) and a robust `clientSecret` (32 random hex characters) using PHP's cryptographically secure `random_bytes()` function. An SQL `INSERT` statement persists the client configuration into the database. Finally, it outputs the generated credentials clearly to the console.

# EXAMPLES
Create an OAuth client for a frontend SPA:
`php spp.php oauth:client:create "Frontend Portal" "https://portal.example.com/callback"`
