# NAME

`spp lang:set` - Set a translation for a key

# SYNOPSIS

`php spp.php lang:set <key> <locale> <translation> [--app=<app_context>]`

# PURPOSE

The `lang:set` command allows direct insertion or updating of a localized string inside the translation repository. This provides a quick programmatic method to manage localization definitions from the command line without using the UI.

# OPTIONS AVAILABLE

- `<key>`: The unique translation key code.
- `<locale>`: The target language locale code (e.g., `en`, `es`).
- `<translation>`: The translated string value.
- `--app=<app_context>`: (Optional) The application context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

`LangSetCommand` processes positional arguments systematically to extract the `key`, `locale`, and `translation` values. It demands that all three are present or displays usage instructions.
It then shifts execution into the application context using `\SPP\Scheduler::withContext`. After guaranteeing the `spplang` module is active using `\SPP\Module::loadModule('spplang')`, it delegates the actual persistence to the module via `\SPPMod\SPPLang\SPPLang::saveTranslation($key, $locale, $translation, 'active')`. This static method handles UPSERT logic on the underlying database tables, associating the translated text with the key and locale under an 'active' status.

# EXAMPLES

**Set the greeting translation for Spanish:**
```bash
php spp.php lang:set hello es "Hola"
```
