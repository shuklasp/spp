# NAME
**scim:test:user** - Test SCIM User Provisioning locally

# SYNOPSIS
`php spp.php scim:test:user <username> [email]`

# PURPOSE
Simulates a SCIM (System for Cross-domain Identity Management) payload to test user provisioning directly via the command line, bypassing HTTP network layers and authentication guards.

# OPTIONS AVAILABLE
- `<username>` : **Required.** The desired username for the provisioned test user.
- `[email]` : **Optional.** The email address of the user. If omitted, defaults to `<username>@example.com`.

# UNDER THE HOOD ACTIVITY
To test the SCIM implementation without triggering TokenGuard middleware or requiring an actual HTTP request, the command synthesizes an array representing a standard SCIM v2.0 JSON payload containing schemas, userName, emails, name, and active status. It instantiates the `\SPPMod\SPPAuth\SCIMHandler` class. By utilizing PHP's `ReflectionClass`, it dynamically alters the accessibility of the `createUser` method (which is normally protected or private) and invokes it directly with the payload array. Output buffering (`ob_start()`) is used to capture the JSON response string returned by the handler, which is then printed to the console alongside a success banner.

# EXAMPLES
Test provisioning a standard user:
`php spp.php scim:test:user jdoe jdoe@company.local`

Test provisioning without an explicit email:
`php spp.php scim:test:user admin_user`
