## `view:page:add`

**Purpose**: Add a new page route to an app

### Synopsis
```bash
php spp.php view:page:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:page:add --name=<route> --url=<target> [--app=default] [--source=yaml|db]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php.
- `--name=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php.
- `--url=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php.
- `--source=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: page.

