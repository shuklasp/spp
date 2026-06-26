# ent:edit

## NAME
ent:edit - Edit an existing SPPEntity definition

## SYNOPSIS
`php spp.php ent:edit [EntityName] [OPTIONS]`

## PURPOSE
Facilitates CLI-driven or interactive editing of Entity YAML configurations, including structural relationships, database bindings, and raw attribute mutation.

## OPTIONS AVAILABLE
- `--table=TableName`: Updates the physical database table mapped to the entity.
- `--extends=Class`: Replaces the logical parent entity class.
- `--login=true|false`: Toggles SPP Login feature support flag.
- `--add-field="name:type"`: Comma-separated list for injecting or modifying schema attributes.
- `--remove-field="name"`: Comma-separated list of attributes to purge.
- `--add-relation="Target:Type:ForeignKey:PivotTable"`: Appends relation definitions.
- `--remove-relation=index`: Removes a specific relation structure by integer array index.

## UNDER THE HOOD ACTIVITY
If no arguments are provided, it forces an interactive wizard by listing available entities from `SPPEntity::listAvailableEntities()` and taking user input via a `prompt()` wrapper. It locates the entity config via `SPPEntity::getEntityConfigFile()`. It parses the source YAML, allowing either flag-driven arrays manipulations or a deeply nested interactive CLI loop. Through CLI flags, it interprets the parameters (like auto-computing many-to-many pivot tables logic) and dynamically mutates the YAML array structure. It serializes and persists the updated state strictly via `\SPPMod\SPPDB\SPPEntity::saveEntityDefinition()`.

## EXAMPLES
```bash
php spp.php ent:edit Student --table=new_students --add-field="graduation_year:int"
php spp.php ent:edit User --add-relation="Role:ManyToMany:user_id:user_role"
```
