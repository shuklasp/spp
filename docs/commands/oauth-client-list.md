# NAME
**oauth:client:list** - List all OAuth 2.0 Client Apps

# SYNOPSIS
`php spp.php oauth:client:list`

# PURPOSE
Displays a registry of all active OAuth 2.0 Client Applications provisioned on the server.

# OPTIONS AVAILABLE
This command takes no arguments or options.

# UNDER THE HOOD ACTIVITY
The command uses the `SPPDB` database abstraction layer to query the `oauth_clients` table. It performs a `SELECT` operation requesting the `id`, `name`, `redirect_uri`, and `created_at` fields, sorting the results chronologically descending by creation date. The raw result set is parsed and, if the global `printTable()` CLI helper is available, rendered as a neatly formatted ASCII table. Otherwise, it falls back to printing the list sequentially in a standard text format.

# EXAMPLES
View all registered clients:
`php spp.php oauth:client:list`
