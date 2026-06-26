# NAME

`admin:bootstrap`

# SYNOPSIS

`php spp.php admin:bootstrap`

# PURPOSE

Initializes the SPP Administration environment by provisioning the default admin account directly into the lightweight XDB (XML Database).

# OPTIONS AVAILABLE

This command accepts no specific options.

# UNDER THE HOOD ACTIVITY

When executed, this command connects to the built-in XDB XML database by initializing an `SPPDB` instance with the connection string `xdb:dbname=default`. It sequentially inspects the database to ensure the foundational identity tables (`users`, `roles`, and `userroles`) exist, and if missing, executes the respective `CREATE TABLE` queries with string and auto-increment types. 

After ensuring schema compliance, it queries the `users` table for the existence of the `admin` account. If it does not exist, the script explicitly verifies the existence of the 'Admin' role, creating it and granting it an ID if absent. Finally, the command creates a secure hash of the default password (`admin123`) via PHP's `password_hash()` and directly inserts the new administrative user into the `users` table, then binds the user and role together via an `INSERT` into the `userroles` table. All activity is logged synchronously to standard output.

# EXAMPLES

Initialize the administration environment:
```bash
php spp.php admin:bootstrap
```
