# NAME

`api:key:generate`

# SYNOPSIS

`php spp.php api:key:generate "<name>"`

# PURPOSE

Generates a new, highly secure, permanent API key token and records it in the database for client authentication.

# OPTIONS AVAILABLE

- `"<name>"` (Required Positional Argument) : A human-readable identifier or description for the generated key. Typically the name of the client or service.

# UNDER THE HOOD ACTIVITY

The command validates that the required `<name>` positional argument is present at `$args[2]`, throwing an error if absent. The API token itself is generated using a secure cryptographically random generator via `bin2hex(random_bytes(32))`, resulting in a robust 64-character hexadecimal string.

A standard `\SPPMod\SPPDB\SPPDB` database connection is instantiated. The command performs a basic schema verification by asserting the existence of the `api_keys` table. A unique identifier for the database row is created using `uniqid()`. A parameterized `INSERT` query is then executed on the `api_keys` table to persist the generated row ID, the descriptive token name, the raw API token, an active status flag (integer `1`), and the creation timestamp using `NOW()`. After successfully writing to the database, the raw API key token is echo'ed to the console for the user to securely capture.

# EXAMPLES

Generate an API key for the mobile application:
```bash
php spp.php api:key:generate "MobileApp_Production"
```
