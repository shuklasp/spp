## `man:generate`

**Description**: Generate highly detailed man-pages in Markdown and UNIX roff formats

### Synopsis
```bash
php spp.php man:generate [OPTIONS]
```

### Options
- `--force` : Boolean flag. Extracted via static analysis from ManGenerateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \ReflectionClass.

