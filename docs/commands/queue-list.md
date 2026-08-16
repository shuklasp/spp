## `queue:list`

**Purpose**: List all jobs currently in the queue

### Synopsis
```bash
php spp.php queue:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.

