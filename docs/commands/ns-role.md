## NAME

**role** - Role-based access control (RBAC) and security privilege management

## PURPOSE

The `role` namespace provides CLI commands for defining security roles and inspecting access control groups. Roles are bound to users and evaluated by permission guards throughout the framework.

## UNDER THE HOOD ACTIVITY

Commands in the `role` namespace query and modify the `spp_roles` and `spp_user_roles` database tables. Output is formatted for both human operators and JSON consumers.

To view details for a specific command, execute `php spp.php man role:<command>` (e.g., `php spp.php man role:list`).
