# NAME
spp config - Manage framework and application configuration

# SYNOPSIS
`php spp.php config [get|set|delete|list|cache|clear] [key] [value]`

# PURPOSE
Provides a multi-tool interface for interacting directly with the `\SPP\SPPConfig` manager. It allows developers to query, mutate, inspect, and cache application configuration values securely from the command line.

# OPTIONS AVAILABLE
- `get <key>` : Retrieves and outputs the value of a specific configuration key.
- `set <key> <value>` : Sets or overrides a configuration key with a specified string value.
- `delete <key>` : Removes a configuration key entirely.
- `list [appname]` : Dumps the entire configuration payload as an ASCII table.
- `cache [appname]` : Compiles and locks the configuration map to disk for massive performance gains.
- `clear [appname]` : Removes the compiled configuration cache lock.

# UNDER THE HOOD ACTIVITY
Parses the `$action` argument and behaves as a facade to static `SPPConfig` methods.
- **get:** Fetches via `SPPConfig::get($key)`. If the underlying data is an array/object, it serializes it using `json_encode` before outputting; otherwise, it outputs scalars directly.
- **set / delete:** Directly manipulates the runtime configuration cache via `SPPConfig::set()` and `SPPConfig::delete()`.
- **cache:** Triggers `SPPConfig::compile($appname)`, aggregating scattered config files and serializing them into an optimized PHP static cache file.
- **clear:** Invokes `SPPConfig::clearCompiled($appname)`, forcing real-time parsing.
- **list:** Uses `SPPConfig::getAll($appname)` to retrieve the full dictionary. It dynamically constructs a tabular format via `\SPP\CLI\Console::printTable()`, encoding arrays for inline readability.

# EXAMPLES
Get database host:
`php spp.php config get database.host`

Cache config for production:
`php spp.php config cache frontend`
