## `make:entity`

**Description**: Create a new SPPEntity definition

### Synopsis
```bash
php spp.php make:entity [OPTIONS]
```

### Extended Usage
```text
Creates a new SPPEntity definition. If run without flags, it will launch an interactive wizard.

Usage:
  php spp.php make:entity [EntityName] [OPTIONS]

Options:
  --app=AppName         Specify the application context (defaults to "default").
  --table=TableName     Specify the database table name (defaults to lowercase plural of EntityName).
  --extends=Class       Specify the parent entity class (e.g. "\App\Entities\User").
  --login=true|false    Enable or disable SPP Login Support for this entity.
  --fields="f1:type,f2" Define attributes. Format: "name:type". Default type is varchar(255).
  --relations="Rel"     Define relationships. Format: "Target:Type:ForeignKey:PivotTable".
                        Example: "\App\Entities\Course:ManyToMany:student_id:student_courses"
  --api, --resource     Generate a REST API controller for this entity.

Examples:
  Interactive Mode:
    php spp.php make:entity Student

  Non-Interactive Mode:
    php spp.php make:entity Student --table=spp_students --fields="name:varchar(255),age:int" --extends="\App\Entities\User" --login=true --relations="\App\Entities\Profile:OneToOne:student_id"
```

### Options
- `--fields=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--app=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--table=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--extends=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--login=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--relations=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--api` : Boolean flag. Extracted via static analysis from MakeEntityCommand.php
- `--resource` : Boolean flag. Extracted via static analysis from MakeEntityCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: SPPEntity.

