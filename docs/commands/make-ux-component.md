## `make:ux-component`

**Purpose**: Scaffold a new SPP-UX reactive component

### Synopsis
```bash
php spp.php make:ux-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:ux-component <ComponentName> [--template=external]

```

### Options Available
- `--template=external` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: reactive, SPP.

