# NAME
`make:blade` - Scaffold a new Blade template (Drishyam Paradigm)

# SYNOPSIS
`php spp.php make:blade <ViewName> [--name=<ViewName>]`

# PURPOSE
The `make:blade` command generates a new SPP Blade view file specifically tailored for the Drishyam Paradigm. This creates a visually stylized starter template integrating SPP's internal Blade compilation engine.

# OPTIONS AVAILABLE
- `<ViewName>` or `--name=<ViewName>` (string): The name of the Blade view you wish to create. It automatically ensures the `.blade.php` extension is appended if not provided.

# UNDER THE HOOD ACTIVITY
When executed, this command reads the current execution context via `getContext()` to determine which app namespace to target (e.g. `default`, `admin`). It resolves the view folder to `SPP_APP_DIR/resources/views/<context>/`. If the `<ViewName>.blade.php` file does not exist, it forcefully creates the directory structure. 
It writes a predefined Drishyam layout scaffold containing basic HTML and CSS boilerplate, an `app` layout extension directive (`@extends('layouts.app')`), and specific hero banner code styled with a linear-gradient and inter fonts. The resulting file acts as a standalone interactive view with a placeholder JavaScript alert wired to a primary button.

# EXAMPLES
**1. Scaffold a dashboard view:**
```bash
php spp.php make:blade dashboard
```

**2. Scaffold a dashboard view explicitly:**
```bash
php spp.php make:blade --name=dashboard
```
