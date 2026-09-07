## `pkg:install`

**Description**: Download and install a native SPP app or external package from a URL, local path, or central registry

### Synopsis
```bash
php spp.php pkg:install [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php pkg:install <source_or_name> <app_name> [--type=native|external] [--route=/path]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \ZipArchive.

