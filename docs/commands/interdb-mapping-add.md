## `interdb:mapping:add`

**Purpose**: Add a new InterDB mapping

### Synopsis
```bash
php spp.php interdb:mapping:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php interdb:mapping:add <alias> <engine> <table>

```

### Options Available
- `--mappings` : Boolean flag or option. Extracted via static analysis from InterdbMappingAddCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: InterDB.

