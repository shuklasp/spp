## `test:run`

**Purpose**: Runs Parikshak evaluation for an entity or the whole suite

### Synopsis
```bash
php spp.php test:run [OPTIONS]
```

### Options Available
- `--coverage` : Boolean flag or option. Extracted via static analysis from TestRunCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: parikshak.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\Parikshak\Parikshak.

