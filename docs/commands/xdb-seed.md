# NAME

xdb:seed - Run SPP_XDB Database Seeders

# SYNOPSIS

`php spp.php xdb:seed [--class=<SeederClassName>]`

# PURPOSE

The `xdb:seed` command is used to populate the database with initial records by executing seeder classes defined in the `SPP_XDB` module. This is typically run after database migrations to load essential application configuration data, default user accounts, or randomized dummy data for local development testing.

# OPTIONS AVAILABLE

*   `--class=<SeederClassName>` (Optional)
    Specifies a single, specific seeder class to execute. If omitted, the command will execute the master `DatabaseSeeder` or run all available seeders depending on the module's internal logic.

# UNDER THE HOOD ACTIVITY

The command parses the incoming CLI arguments to detect the presence of the `--class=` flag. If found, it extracts the class name (e.g., `UserSeeder`) into the `$specificSeeder` variable.

It then establishes a database connection via `new SPP_XDB()` and instantiates the `SPPMod\SPPXDB\SeederManager`.

The core execution invokes `$mgr->seed($specificSeeder)`. If `$specificSeeder` is provided, the `SeederManager` dynamically instantiates only that specific class from the `seeds` directory and calls its `run()` method to execute its database insert statements. 

If `$specificSeeder` is `null`, the manager typically looks for a default orchestrator class (like `DatabaseSeeder`) or scans the directory to execute all seeders in a defined sequence. The `seed()` method tracks the total number of individual seeder classes that were successfully executed and returns this integer count. The command then outputs a summary message indicating the total number of executed seeders.

# EXAMPLES

**Run the default suite of database seeders:**
```bash
php spp.php xdb:seed
```

**Execute only a specific seeder class:**
```bash
php spp.php xdb:seed --class=DummyDataSeeder
```
