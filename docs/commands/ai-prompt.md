## `ai:prompt`

**Description**: Send a prompt to the AI provider

### Synopsis
```bash
php spp.php ai:prompt [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ai:prompt \
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from AiPromptCommand.php
- `--provider=` : Expects a value. Extracted via static analysis from AiPromptCommand.php
- `--model=` : Expects a value. Extracted via static analysis from AiPromptCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: sppai.
- Bootstraps a full application execution context (Scheduler::withContext).

