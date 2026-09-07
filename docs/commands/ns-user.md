## NAME

**user** - User identity, authentication credentials, and account lifecycle management

## PURPOSE

The `user` namespace provides core CLI commands for managing user identity, credential hashing, password resets, and account removal across framework databases. It serves as the primary backend engine for the `sppadmin` Identity & Access management console.

## UNDER THE HOOD ACTIVITY

Commands in the `user` namespace directly query and update the `spp_users` relational table via `\SPPMod\SPPDB\SPPDB`. Operations enforce secure password hashing using bcrypt (`PASSWORD_BCRYPT`), handle role mappings, and support `--json` structured outputs for web integration.

To view details for a specific command, execute `php spp.php man user:<command>` (e.g., `php spp.php man user:list`).
