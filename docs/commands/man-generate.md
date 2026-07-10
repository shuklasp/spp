## `man:generate`

**Description**: Generate highly detailed man-pages in Markdown and UNIX roff formats

### Synopsis
```bash
php spp.php man:generate [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \ReflectionClass.

