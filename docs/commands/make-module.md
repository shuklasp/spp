# NAME
`make:module` - Create a new SPP module

# SYNOPSIS
`php spp.php make:module <name> [--scope=spp|contrib|app]`

# PURPOSE
The `make:module` command scaffolds the architectural boilerplate required to construct a modular, pluggable application extension within SPP. It constructs the essential module directory, the autoloader manifest (`module.xml`), and the core bootstrap PHP class.

# OPTIONS AVAILABLE
- `<name>` (string, required): The name of the module (e.g. `Blog`, `Forum`).
- `--scope=<spp|contrib|app>` (string, optional): Defines the organizational boundary of the module.
    - `app` (default): Installs into `SPP_APP_DIR/spp/modules/app/`.
    - `spp`: Installs globally into the core framework at `SPP_BASE_DIR/modules/spp/`.
    - `contrib`: Installs into the community plugin directory at `SPP_BASE_DIR/modules/contrib/`.

# UNDER THE HOOD ACTIVITY
It sanitizes the requested module name, aggressively stripping all non-alphanumeric characters using `preg_replace('/[^a-zA-Z0-9]/', '', $name)` and enforcing lowercase. It calculates the absolute target directory based on the `--scope` argument.
It then physically executes `mkdir()` to generate the directory structure. 
First, it generates a robust `module.xml` manifest detailing the module's name, version, description, namespace (`SPPMod\{Name}`), and explicitly maps the autoloader rules referencing the primary class file.
Second, it generates the primary bootstrap class `class.{name}.php` extending `\SPP\SPPObject`, setting up the constructor logic. The CLI also provides a `renderAdminUI()` bridge to allow modular scaffolding directly from the visual SPP admin dashboard.

# EXAMPLES
**1. Create a local application module:**
```bash
php spp.php make:module Forum --scope=app
```
