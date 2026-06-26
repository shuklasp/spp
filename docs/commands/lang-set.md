## `lang:set`

**Purpose**: Set a translation for a key

### Synopsis
```bash
php spp.php lang:set [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php lang:set <key> <locale> <translation>

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from LangSetCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: spplang.
- Bootstraps a full application execution context via Scheduler.

