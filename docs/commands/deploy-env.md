## `deploy:env`

**Purpose**: Manage remote environment variables securely

### Synopsis
```bash
php spp.php deploy:env [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:env [target_uri] push --key=MY_KEY --value=MY_VALUE [--key_api=YOUR_API_KEY]

```

### Options Available
- `--key_api=` : Expects a value. Extracted via static analysis.
- `--key=` : Expects a value. Extracted via static analysis.
- `--value=` : Expects a value. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.

