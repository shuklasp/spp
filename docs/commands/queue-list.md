## `queue:list`

**Description**: List all jobs currently in the queue

### Synopsis
```bash
php spp.php queue:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from QueueListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\SPPDB\SPPDB.

