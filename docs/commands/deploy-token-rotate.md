## `deploy:token:rotate`

**Description**: Rotate the secure deployment gateway token on both local and remote environments with zero downtime

### Synopsis
```bash
php spp.php deploy:token:rotate [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployTokenRotateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: deployment, token.

