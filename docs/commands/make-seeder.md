# NAME
`make:seeder` - Create a new Database Seeder class

# SYNOPSIS
`php spp.php make:seeder <SeederName> [--app=appname]`

# PURPOSE
The `make:seeder` command scaffolds a Database Seeder PHP class. Seeders are heavily utilized to programmatically inject mock data, default states, or testing artifacts into the application database structure.

# OPTIONS AVAILABLE
- `<SeederName>` (string, required): The target identifier for the Seeder class. If missing, it prompts interactively.
- `--app=<appname>` (string, optional): Determines the target execution namespace (resolves to `src/{app_name}/seeders/`).

# UNDER THE HOOD ACTIVITY
The command sanitizes the provided string, automatically appending `Seeder` if it does not naturally terminate with it (e.g. `User` -> `UserSeeder`).
It dynamically calculates the directory `src/{app_name}/seeders`, constructing the folder hierarchy forcefully.
It generates a raw PHP string building the `App\Seeders` namespace and a class exposing a public `run(SPPDB $db)` method. A boilerplate `$db->execute_query()` string is heavily commented inline as an architectural example. Finally, it commits the string natively to the file system using `file_put_contents`.

# EXAMPLES
**1. Scaffold an Admin seeder:**
```bash
php spp.php make:seeder AdminUser --app=core
```
