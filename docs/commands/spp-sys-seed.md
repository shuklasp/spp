# NAME

`sys:seed` - Run all database seeders for an application.

# SYNOPSIS

`php spp sys:seed [appname]`

# PURPOSE

The `sys:seed` command is utilized to populate the application's database with initial or dummy data. It discovers and executes database seeder classes located in the specified application's seeders directory.

# OPTIONS AVAILABLE

- `[appname]` (Optional): The name of the application whose seeders you want to run. If not provided, it defaults to `default`.

# UNDER THE HOOD ACTIVITY

Upon execution, the `sys:seed` command performs the following operations:
1. **Argument Parsing**: It extracts the application name from the CLI arguments (`$args[2]`), falling back to `'default'` if missing.
2. **Path Resolution**: It resolves the expected seeders directory path based on the application name: `SPP_APP_DIR . "/src/{$appname}/seeders"`. It aborts gracefully if this directory does not exist.
3. **Database Connection**: It establishes an active database connection by instantiating `SPPMod\SPPDB\SPPDB()`.
4. **Seeder Discovery**: It scans the seeders directory using `glob()` to locate all files matching the `*Seeder.php` pattern.
5. **Execution Loop**: For each matched file:
   - The file is included via `require_once`.
   - The class name is derived from the file name and prepended with the `\App\Seeders\` namespace.
   - If the class exists, an instance is created.
   - The command invokes the `run($db)` method on the seeder instance, passing the active database connection, allowing the seeder to execute its SQL queries or ORM logic.

# EXAMPLES

**Run seeders for the default app:**
```bash
php spp sys:seed
```

**Run seeders for a specific application named 'admin':**
```bash
php spp sys:seed admin
```
