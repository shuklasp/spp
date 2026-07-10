## `make:polyglot-partial`

**Description**: Scaffold a new external polyglot partial service file (Python/Node/Go)

### Synopsis
```bash
php spp.php make:polyglot-partial [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:polyglot-partial <ModuleName.py|.js|.go> [--lang=python|node|go] [--app=AppName]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: external.

