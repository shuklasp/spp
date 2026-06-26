# NAME
**iam:roles** - List all Roles and Entity Role Assignments

# SYNOPSIS
`php spp.php iam:roles list`

# PURPOSE
Displays a comprehensive view of the system's identity and access management configurations, specifically detailing defined roles and current entity-role bindings.

# OPTIONS AVAILABLE
- `list` : The default and currently only supported action, which triggers the display of roles.

# UNDER THE HOOD ACTIVITY
The command checks the action argument (defaulting to `list`). Using the `SPPDB` database layer, it executes two distinct SQL queries. First, it queries the `roles` table and outputs all system roles (ID, Role Name, Description). It then executes a `JOIN` query between the `entity_roles` and `roles` tables to fetch all active assignments, mapping `target_class` and `target_id` (the assigned entity) to their designated `role_name`. Both sets of data are printed as ASCII tables utilizing the global `printTable()` helper function.

# EXAMPLES
List roles and assignments:
`php spp.php iam:roles list`
