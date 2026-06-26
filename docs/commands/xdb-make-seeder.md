# NAME

xdb:make:seeder - Create a new SPP_XDB seeder file

# SYNOPSIS

`php spp.php xdb:make:seeder <name_of_seeder>`

# PURPOSE

The `xdb:make:seeder` command generates a new database seeder class for the `SPP_XDB` module. Seeders are used to populate the database with initial, default, or dummy data for testing and development, allowing developers to quickly bootstrap a functional application state.

# OPTIONS AVAILABLE

*   `<name_of_seeder>` (Required)
    The name of the seeder class to generate. It is conventional to name these classes based on the entity they populate, suffixed with "Seeder" (e.g., `UserSeeder`, `RolesTableSeeder`).

# UNDER THE HOOD ACTIVITY

The execution begins by parsing the command-line arguments to extract the seeder name. It skips over flags, the PHP script name, and the command name itself, capturing the first positional string as the `$name`. If no name is provided, execution halts and a usage hint is displayed.

After capturing the name, the command creates a new instance of the `SPPMod\SPPXDB\SPP_XDB` base database object. This connection object is injected into a newly instantiated `SPPMod\SPPXDB\SeederManager`.

The CLI then calls `$mgr->create($name)`. Inside the `SeederManager`, the framework resolves the designated `seeds` directory path for the `XDB` module. It formats the provided name into a valid PHP class name, loads a seeder template stub (which typically includes a `run()` method where database insert operations belong), and writes the `.php` file to the filesystem. Finally, the manager returns the path to the written file, which is output to the console to confirm successful creation.

# EXAMPLES

**Generate a new database seeder class for products:**
```bash
php spp.php xdb:make:seeder ProductSeeder
```
