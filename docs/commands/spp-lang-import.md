# NAME

`spp lang:import` - Import JSON language file into active database translation overrides

# SYNOPSIS

`php spp.php lang:import [locale] [--app=<app_context>]`

# PURPOSE

The `lang:import` command is used to parse a JSON language file from the filesystem and populate or update the database translation repository table, enabling seamless GitOps workflows where translation files are synced into active application database environments.

# OPTIONS AVAILABLE

- `[locale]`: (Optional) The locale to import translations for. Defaults to `en`.
- `--app=<app_context>`: (Optional) The application context to execute the import within. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

`LangImportCommand` processes CLI arguments to isolate the `locale` and the `--app` context.
It executes within an isolated closure wrapped by `\SPP\Scheduler::withContext`. It explicitly loads the `spplang` module using `\SPP\Module::loadModule`. 
The command verifies the existence of `SPP_APP_DIR . "/src/{$appName}/translations/{$locale}.json"` (or `resources/translations/{$locale}.json`). It reads and decodes the JSON dictionary. For each `key => value` pair, it calls `\SPPMod\SPPLang\SPPLang::saveTranslation($key, $locale, $val, 'active')`, cleanly syncing them into the database repository.

# EXAMPLES

**Import all English translations from the filesystem JSON to the database:**
```bash
php spp.php lang:import en
```
