## `ent:edit`

**Description**: Edit an existing SPPEntity definition

### Synopsis
```bash
php spp.php ent:edit [OPTIONS]
```

### Extended Usage
```text
Edits an existing SPPEntity definition. If run without flags, it will launch an interactive wizard.
Passing any of the double-dash flags will bypass the wizard and execute a non-interactive edit.

Usage:
  php spp.php ent:edit [EntityName] [OPTIONS]

Options:
  --table=TableName            Update the database table name.
  --extends=Class              Update the parent entity class (e.g. "\App\Entities\User").
  --login=true|false           Enable or disable SPP Login Support for this entity.
  --add-field="name:type"      Add or update attributes. Format: "name:type" (comma-separated).
  --remove-field="name"        Remove attributes by name (comma-separated).
  --add-relation="Target:..."  Add relationships. Format: "Target:Type:ForeignKey:PivotTable" (comma-separated).
  --remove-relation=index      Remove a relationship by its integer index.

Examples:
  Interactive Mode:
    php spp.php ent:edit Student

  Non-Interactive Edit:
    php spp.php ent:edit Student --table=new_students --add-field="graduation_year:int" --remove-field="age"
```

### Options
- `--table=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--extends=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--login=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--add-field=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--remove-field=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--add-relation=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--remove-relation=` : Expects a value. Extracted via static analysis from EntEditCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.

