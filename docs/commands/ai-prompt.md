## `ai:prompt`

**Purpose**: Send a prompt to the AI provider

### Synopsis
```bash
php spp.php ai:prompt [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ai:prompt \
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.
- `--provider=` : Expects a value. Extracted via static analysis.
- `--model=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: sppai.
- Bootstraps a full application execution context via Scheduler.

