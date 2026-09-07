## `deploy:env`

**Description**: Manage remote environment variables securely

### Synopsis
```bash
php spp.php deploy:env [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:env [target_uri] push --key=MY_KEY --value=MY_VALUE [--key_api=YOUR_API_KEY]

```

### Options
- `--key_api=` : Expects a value. Extracted via static analysis from DeployEnvCommand.php
- `--key=` : Expects a value. Extracted via static analysis from DeployEnvCommand.php
- `--value=` : Expects a value. Extracted via static analysis from DeployEnvCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.

