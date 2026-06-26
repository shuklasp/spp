# NAME

`spp lang:list` - List all translations

# SYNOPSIS

`php spp.php lang:list [locale] [--app=<app_context>]`

# PURPOSE

The `lang:list` command displays registered translation strings. It allows developers to quickly inspect which translation keys exist for a particular locale within an application context.

# OPTIONS AVAILABLE

- `[locale]`: (Optional) Restrict the output to a specific locale code (e.g., `en`, `fr`).
- `--app=<app_context>`: (Optional) Specify the application context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

The `LangListCommand` determines the locale filter from positional arguments and extracts the `--app` option if provided.
It utilizes `\SPP\Scheduler::withContext` to safely isolate its operations to the requested app context. It attempts to load the `spplang` module by calling `\SPP\Module::loadModule('spplang')` and checks if the `\SPPMod\SPPLang\SPPLang` class exists.
If the module is active, it compiles a filters array. If a locale was specified, it adds it to the filter array. It calls `\SPPMod\SPPLang\SPPLang::getTranslations($filters)` to retrieve an array of translations from the database or translation source.
The command then loops through the resulting array, truncating long translation strings to 35 characters using `substr` and aligning columns nicely with `str_pad` for the `key_code` and `locale` fields.

# EXAMPLES

**List all translations in all locales:**
```bash
php spp.php lang:list
```

**List translations for the French locale in a specific app:**
```bash
php spp.php lang:list fr --app=admin
```
