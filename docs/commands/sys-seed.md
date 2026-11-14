## `sys:seed`

**Purpose**: Discovers and executes all database seeders for a specified application, supporting both modern `SPPSeeder` classes and legacy seeding routines.

### Synopsis
```bash
php spp.php sys:seed [app_name]
```

### Extended Usage
`sys:seed` scans `src/{app}/seeders/` for all `*Seeder.php` files and executes them sequentially.
- Modern `SPPSeeder` subclasses are executed via `run(): void`, and their audit trail is captured and displayed in the terminal output.
- Legacy seeders accepting `(SPPDB $db)` are detected via reflection and executed gracefully with backward compatibility.
- Seeders can also be executed programmatically or triggered via `sppdev` / `sppadmin` Database Studio.

#### Examples:
```bash
# Run seeders for default app
php spp.php sys:seed

# Run seeders for school1 app
php spp.php sys:seed school1
```

### Options Available
- `[app_name]` (argument, optional): The target application directory under `src/` (defaults to `default`).

### Under the Hood Activity
- Scans `src/{app}/seeders/` for matching `*Seeder.php` files.
- Instantiates database connection and dynamically invokes each seeder class.
- Flushes detailed execution logs and record counts to standard output.
