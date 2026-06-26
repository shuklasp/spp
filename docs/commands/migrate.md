# NAME

migrate - Run pending database migrations

# SYNOPSIS

`php spp.php migrate`

# PURPOSE

The `migrate` command is responsible for executing all pending database migrations. It ensures that the database schema is up-to-date with the codebase by running the `up()` methods of any migration classes that have not yet been recorded in the database's migration tracking table.

# OPTIONS AVAILABLE

This command does not accept any specific optional flags or arguments. It relies entirely on the global application context to determine which migrations to run.

# UNDER THE HOOD ACTIVITY

Upon execution, the command determines the active execution environment by calling `\SPP\Scheduler::getContext()`. It prints an initial status message indicating the context for which migrations are being executed.

It then instantiates the `\SPPMod\SPPDB\Migration\SPPMigrationManager`, passing the active context into its constructor. The core logic is delegated to the manager's `$manager->runPending()` method. 

Under the hood, the `SPPMigrationManager` scans the target `db/migrations` directory for PHP class files. It cross-references the filenames found on disk against the internal database tracking table (e.g., `spp_migrations`). For any file not found in the database table, the manager instantiates the migration class, executes its `up()` method (which contains the raw SQL statements or schema builder logic), and then inserts a record into the tracking table to mark the migration as complete.

The `$manager->runPending()` method returns an array of strings representing the names of the migrations that were successfully executed during the current batch. The command evaluates this array; if it is empty, it informs the user that there is "Nothing to migrate". If migrations were executed, it loops through the array and outputs a color-coded console message (`\033[32m`) for each migration class that was successfully processed.

# EXAMPLES

**Execute all pending database migrations:**
```bash
php spp.php migrate
```
