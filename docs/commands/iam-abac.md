# NAME
**iam:abac** - Manage Attribute-Based Access Control (ABAC) policies

# SYNOPSIS
`php spp.php iam:abac [action] [param1] [param2]`

# PURPOSE
Provides an administrative interface to list, create, and delete Attribute-Based Access Control (ABAC) policies within the system.

# OPTIONS AVAILABLE
- `[action]` : The action to perform. Valid options are `list`, `create`, or `delete`. Defaults to `list`.
- `[param1]` : When action is `create`, this is the `Permission` name. When action is `delete`, this is the Policy `ID`.
- `[param2]` : When action is `create`, this specifies the `Condition Logic`.

# UNDER THE HOOD ACTIVITY
The command interacts directly with the `abac_policies` table via the `SPPDB` database abstraction. 
- In **list** mode, it queries the `abac_policies` table for all existing records and displays them. If the globally defined `printTable` function is available, it renders an ASCII table; otherwise, it outputs a raw list.
- In **create** mode, it accepts a `permission` string and a `condition_logic` string. If omitted from CLI arguments, it prompts the user. The new policy is inserted into the database with a default status of `active`.
- In **delete** mode, it deletes the specific policy from the `abac_policies` table matching the provided ID argument.

# EXAMPLES
List all ABAC policies:
`php spp.php iam:abac list`

Create a new policy for secure data reading:
`php spp.php iam:abac create "read:secure_data" "user.department == 'IT'"`

Delete policy ID 5:
`php spp.php iam:abac delete 5`
