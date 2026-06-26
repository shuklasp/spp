# NAME

xdb:make:migration - Create a new SPP_XDB migration file

# SYNOPSIS

`php spp.php xdb:make:migration <name_of_table>`

# PURPOSE

The `xdb:make:migration` command is utilized within the `SPP_XDB` module to scaffold a new database migration file. Unlike the standard `migrate:make` command, this command is specifically tailored for the `SPP_XDB` advanced database abstraction layer, wiring the generated stubs into the `XDB` migration ecosystem.

# OPTIONS AVAILABLE

*   `<name_of_table>` (Required)
    A descriptive name for the migration, typically reflecting the table being created or modified (e.g., `create_invoices_table`).

# UNDER THE HOOD ACTIVITY

The command iterates over the raw CLI arguments provided by the user. It explicitly ignores any flags starting with `--` and filters out the framework's base script names (`spp.php`, `spp/spp.php`) and the command signature itself. The first remaining argument is captured as the `$name`. If no name is resolved, the command prints a usage string and exits.

Once a name is secured, the command instantiates the core `SPPMod\SPPXDB\SPP_XDB` database connection instance. It then passes this database instance into a new `SPPMod\SPPXDB\MigrationManager` object.

The command delegates the file creation to `$mgr->create($name)`. The `MigrationManager` utilizes the `XDB` module's configuration paths, generates a timestamped filename (ensuring migrations run in chronological order), populates an `XDB`-specific PHP class stub with `up()` and `down()` transaction methods, and writes the file to disk. The manager returns the absolute path of the newly created file, which the CLI echoes back to the developer.

# EXAMPLES

**Create a new XDB migration for the users table:**
```bash
php spp.php xdb:make:migration create_users_table
```
