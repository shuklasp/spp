## `deploy:token:rotate`

**Purpose**: Rotate the secure deployment gateway token on both local and remote environments with zero downtime

### Synopsis
```bash
php spp.php deploy:token:rotate [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: deployment, token.

