## `lang:set`

**Description**: Set a translation for a key

### Synopsis
```bash
php spp.php lang:set [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php lang:set <key> <locale> <translation>

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangSetCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).

