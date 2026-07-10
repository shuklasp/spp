# NAME

`spp lang:export` - Export active database translation overrides into JSON language file

# SYNOPSIS

`php spp.php lang:export [locale] [--app=<app_context>]`

# PURPOSE

The `lang:export` command is used to fetch all active translation override records from the database repository for a given locale and cleanly dump them into the corresponding JSON language file for GitOps synchronization and filesystem-based translation loading.

# OPTIONS AVAILABLE

- `[locale]`: (Optional) The locale to export translations for. Defaults to `en`.
- `--app=<app_context>`: (Optional) The application context to execute the export within. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

`LangExportCommand` processes CLI arguments to isolate the `locale` and the `--app` context.
It executes within an isolated closure wrapped by `\SPP\Scheduler::withContext`. It explicitly loads the `spplang` module using `\SPP\Module::loadModule`. 
The command fetches active translations via `\SPPMod\SPPLang\SPPLang::getTranslations(['locale' => $locale, 'status' => 'active'])`. It builds an associative dictionary array of `key_code => translation` pairs and serializes it into clean, beautifully formatted JSON (`JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE`). It writes the resulting JSON file directly to `SPP_APP_DIR . "/src/{$appName}/translations/{$locale}.json"`.

# EXAMPLES

**Export all active English translations to the filesystem:**
```bash
php spp.php lang:export en
```
