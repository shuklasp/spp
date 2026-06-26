# NAME
**make:migration** - Create a new database migration file

# SYNOPSIS
`php spp.php make:migration <migration_name>`

# PURPOSE
Generates a boilerplate PHP migration class file within the current application context. This file is used to define structural changes to the database schemas such as creating or modifying tables.

# OPTIONS AVAILABLE
- `<migration_name>` : **Required.** A descriptive name for the migration. E.g., `create_users_table`.

# UNDER THE HOOD ACTIVITY
The script begins by asserting that the `<migration_name>` argument is provided. It then determines the active module or application context via `\SPP\Scheduler::getContext()`. It formats the provided migration name by aggressively converting it to snake_case, and subsequently transforming it into a PascalCase class name (e.g., `CreateUsersTable`). A timestamp format (`Y_m_d_His`) is generated and prepended to the filename to ensure chronological ordering. The CLI then ensures the directory `SPP_APP_DIR/src/<context>/migrations` exists, creating it with `0777` permissions if necessary. Finally, it generates a heredoc PHP template inheriting from `\SPPMod\SPPDB\Migration\SPPMigration` and saves it to the path, outputting the file location in green ANSI text.

# EXAMPLES
Create a migration to add an orders table:
`php spp.php make:migration create_orders_table`
