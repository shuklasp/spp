# NAME
`make:blade-scaffold` - Create a full stack Blade scaffold (Entity, YAML Form, Controller, Blade Views)

# SYNOPSIS
`php spp.php make:blade-scaffold [EntityName]`

# PURPOSE
The `make:blade-scaffold` command is the ultimate Rapid Application Development (RAD) tool within SPP. Given an entity name, it generates a complete vertical slice of functionality: Database Entity configuration, UI Form generation via YAML, Blade index and edit views, and the PHP logic entry point required to run the CRUD operations.

# OPTIONS AVAILABLE
- `[EntityName]` (string, optional): The name of the data model you wish to scaffold (e.g. `Student`, `Product`). If omitted, the CLI will prompt for it interactively.

# UNDER THE HOOD ACTIVITY
This command handles a complete MVC generation lifecycle interactively:
1. **Interactive Prompting**: Prompts for `Entity Name`, `App Name (Context)` (defaults to current context), and `Table Name` (defaults to the plural, lowercase entity name).
2. **Entity Definition**: Uses `\SPPMod\SppDb\SPPEntity::saveEntityDefinition()` to physically write a new entity schema configuration for the requested context, defaulting to standard fields like `id`, `name` (varchar), and `description` (text).
3. **YAML Form Generation**: Generates a standard Create/Update form configuration saved to `etc/apps/{app_name}/forms/{entity}.yml`. The form embeds `SPPText` and `SPPTextArea` inputs and sets up automatic form submissions linked to the newly generated entity.
4. **Blade View Synthesis**: Writes both `index.blade.php` (a tabular list view rendering `$items`) and `form.blade.php` (incorporating `@@sppform` and `@@sppbind` directives). It also creates a generic `app.blade.php` layout if it doesn't already exist.
5. **Entry Point Provisioning**: Constructs a standalone `{app_name}_{entity}.php` file at the root. This script initializes the SPP environment, determines the requested action (list, create, edit), uses the ORM (e.g., `\SPPMod\SPPEntity\Product::find($id)`) to fetch records, and defines a `{entity}_form_submitted` callback to intercept POST requests, magically populating the model via `$item->loadFromArray($_POST)` and calling `$item->save()`. Finally, it executes `processForms()` and renders the correct Blade view based on state.

# EXAMPLES
**1. Scaffold a 'Product' CRUD system:**
```bash
php spp.php make:blade-scaffold Product
```
*(Proceed through interactive prompts for Table Name and Context).*
