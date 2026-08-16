## `oauth:client:list`

**Purpose**: List all OAuth 2.0 Client Apps

### Synopsis
```bash
php spp.php oauth:client:list [OPTIONS]
```

### Options Available
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPDB.

