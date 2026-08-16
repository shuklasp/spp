## `import:component`

**Purpose**: Imports pristine air-gapped sovereign UI components

### Synopsis
```bash
php spp.php import:component [OPTIONS]
```

### Options Available
- `--target=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).

