# NAME
`make:entity` - Create a new SPPEntity definition

# SYNOPSIS
`php spp.php make:entity [EntityName] [--app=AppName] [--table=TableName] [--extends=Class] [--login=true|false] [--fields="f1:type,f2"] [--relations="Rel"] [--api] [--resource]`

# PURPOSE
The `make:entity` command is a powerful data-modeling tool. It dynamically generates structural configurations for an SPPEntity instance, mapping object-oriented models to underlying relational databases. It supports both interactive wizard workflows and inline CLI argument execution for complex, CI/CD-friendly schema generation.

# OPTIONS AVAILABLE
- `[EntityName]` (string): The logical name of the entity (e.g. `Student`).
- `--app=<AppName>` (string): The application context context. Defaults to interactive prompt or `default`.
- `--table=<TableName>` (string): Explicitly override the default database table. If not provided, it resolves to the plural lowercase representation of the Entity Name (e.g., `students`).
- `--extends=<Class>` (string): Establish PHP class inheritance for the entity object (e.g., `\App\Entities\User`).
- `--login=<true|false>` (boolean): If `true`, injects SPP Login Support directly into this specific entity layer.
- `--fields="<name:type>"` (string): Comma-separated list of attributes. Defaults to `varchar(255)` if the type is omitted. (e.g., `--fields="name:varchar(255),age:int"`).
- `--relations="<Target:Type:ForeignKey:Pivot>"` (string): A comma-separated list of database relations. Types include `OneToMany` and `ManyToMany`. 
- `--api`, `--resource` (flag): Dynamically scaffolds a boilerplate RESTful JSON controller mapping CRUD methods (`index`, `show`, `store`, `update`, `destroy`) to the newly built entity in `src/{app_name}/controllers/api/`.

# UNDER THE HOOD ACTIVITY
The command checks if it needs to trigger the Interactive Wizard (if arguments like `--fields`, `--extends`, etc., are missing) or if it operates in Headless mode. 
It compiles an extensive schema `$config` array mapping `table`, `id_field`, `sequence`, `extends`, `login_enabled`, `attributes`, and `relations`. For relations, it specifically identifies `ManyToMany` declarations to automatically guess pivot table names (e.g., `student_course`).
It executes `\SPPMod\SPPDB\SPPEntity::saveEntityDefinition()`, directly writing the YAML or serialized schema to disk.
If the `--api` or `--resource` flags are detected, it hooks into standard file manipulation, constructing a `.php` file in the API controllers directory implementing the `\SPP\Core\ResourceController` interface, and wires it to `\SPPMod\SPPEntity\SPPEntity` methods.

# EXAMPLES
**1. Execute the Interactive Wizard:**
```bash
php spp.php make:entity Student
```

**2. Non-interactive CLI Scaffold with API:**
```bash
php spp.php make:entity Course --table=spp_courses --fields="title:varchar(100),credits:int" --relations="\App\Entities\Student:ManyToMany:course_id" --api
```
