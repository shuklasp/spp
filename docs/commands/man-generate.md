## `man:generate`

**Purpose**: Generate highly detailed man-pages in Markdown and UNIX roff formats

### Synopsis
```bash
php spp.php man:generate [OPTIONS]
```

### Options Available
- `--force` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \ReflectionClass.
- Makes outbound HTTP requests to external APIs or services.
- Interacts with the application cache layer (Redis/Memcached).

