# NAME

`spp lang:scan` - Scan directories for new translation keys

# SYNOPSIS

`php spp.php lang:scan [locale] [--app=<app_context>]`

# PURPOSE

The `lang:scan` command is used to parse the application's source code files for localized string calls (like translation helper functions) and automatically register newly discovered translation keys into the SPPLang system for a given locale.

# OPTIONS AVAILABLE

- `[locale]`: (Optional) The locale to generate the discovered keys for. Defaults to `en`.
- `--app=<app_context>`: (Optional) The application context to execute the scan within. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

`LangScanCommand` processes CLI arguments to isolate the `locale` and the `--app` context.
It executes within an isolated closure wrapped by `\SPP\Scheduler::withContext`. It explicitly loads the `spplang` module using `\SPP\Module::loadModule`. 
The command targets the source code directory resolved via `dirname(SPP_BASE_DIR) . '/src'`. It triggers the static method `\SPPMod\SPPLang\SPPLang::scanDirectory($dir, $locale)`. This method performs a recursive filesystem traversal, employing regex to identify SPP translation functional calls (e.g., `__('key')` or `@lang('key')`), and inserts missing keys into the translation registry.
Upon completion, the command receives an array of newly added keys and prints them as a summary list to the CLI.

# EXAMPLES

**Scan the src directory for new keys for the English locale:**
```bash
php spp.php lang:scan en
```
