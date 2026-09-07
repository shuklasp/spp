## `oauth:client:list`

**Description**: List all OAuth 2.0 Client Apps

### Synopsis
```bash
php spp.php oauth:client:list [OPTIONS]
```

### Options
- `--json` : Boolean flag. Extracted via static analysis from OAuthClientListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: SPPDB.

