# NAME

`api:route:list`

# SYNOPSIS

`php spp.php api:route:list`

# PURPOSE

Tabulate and display all exposed REST API routes configured by the SPPAPI module.

# OPTIONS AVAILABLE

This command accepts no specific options.

# UNDER THE HOOD ACTIVITY

When executed, the command first echoes an initialization message to standard output. It then programmatically checks if the `SPPAPI` framework module is loaded into the current environment by verifying the existence of the `\SPPMod\SPPAPI\SPPAPI` class using PHP's native `class_exists()` function. 

If the class is successfully located, indicating the API module is active, the command prints a static ASCII table illustrating the generic REST endpoint structures (e.g., `/api/v1/entities` accepting `GET` and `POST`, and `/api/v1/auth` accepting `POST`). If the class is missing, it notifies the user that the SPPAPI module is not active.

*Note: Currently, this command returns a statically defined list of routes as a demonstration stub, rather than dynamically parsing an application's internal routing tables.*

# EXAMPLES

List the exposed API endpoints:
```bash
php spp.php api:route:list
```
