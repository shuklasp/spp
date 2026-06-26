# NAME

xdb:migrate - Run or roll back SPP_XDB Database Migrations

# SYNOPSIS

`php spp.php xdb:migrate [--rollback] [--steps=<N>]`

# PURPOSE

The `xdb:migrate` command executes the database schema migrations managed by the `SPP_XDB` module. It is capable of both pushing new schema changes to the database by running pending `up()` methods, or reverting previous schema changes by executing `down()` methods.

# OPTIONS AVAILABLE

*   `--rollback` (Optional)
    Instructs the command to reverse the most recent batch of migrations rather than applying new ones.
*   `--steps=<N>` (Optional)
    Used in conjunction with `--rollback` to specify exactly how many individual migration files should be reverted. Defaults to 1 if not specified.

# UNDER THE HOOD ACTIVITY

The CLI script begins by iterating through the argument list to detect the `--rollback` flag and extract the integer value from the `--steps=` flag (defaulting to 1). 

Next, it instantiates an `SPP_XDB` database connection object and passes it to the `SPPMod\SPPXDB\MigrationManager` to handle the business logic.

If the `--rollback` flag is active, the CLI instructs the `MigrationManager` to execute `$mgr->rollback($steps)`. The manager queries the internal `xdb_migrations` tracking table to identify the `$steps` most recently executed migration classes. It instantiates those classes, invokes their `down()` methods to drop tables or remove columns, and then deletes their corresponding records from the tracking table. The command outputs the number of migrations successfully rolled back.

If `--rollback` is not present, the default behavior is to apply pending migrations. The CLI triggers `$mgr->migrate()`. The manager scans the filesystem for migration classes, compares them against the database tracking table, and runs the `up()` method on any class that has not yet been logged. New records are inserted into the tracking table to mark completion. The command concludes by echoing the total number of migrations applied, or notifying the user that there was "Nothing to migrate".

# EXAMPLES

**Run all pending XDB migrations:**
```bash
php spp.php xdb:migrate
```

**Rollback the single most recent migration:**
```bash
php spp.php xdb:migrate --rollback
```

**Rollback the last 3 migrations:**
```bash
php spp.php xdb:migrate --rollback --steps=3
```
