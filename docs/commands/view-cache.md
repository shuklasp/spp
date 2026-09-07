## `view:cache`

**Description**: Pre-compiles all AST views into PHP for optimal performance

### Synopsis
```bash
php spp.php view:cache [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:cache [--app=name]

```

### Options
- `--help` : Boolean flag. Extracted via static analysis from ViewCacheCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \RecursiveIteratorIterator, \RecursiveDirectoryIterator.

