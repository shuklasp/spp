## `make:seeder`

**Purpose**: Scaffolds a modern, fluent database seeder class extending `\SPPMod\SPPDB\SPPSeeder` with built-in entity synthesis, realistic fakes, and workflow-aware fixture generation.

### Synopsis
```bash
php spp.php make:seeder <SeederName> [--app=AppName]
```

### Extended Usage
`make:seeder` provisions a standardized database seeding class under `src/{app}/seeders/{SeederName}Seeder.php`. The scaffolded class extends `\SPPMod\SPPDB\SPPSeeder` and provides:
- Automated entity generation via `$this->seed('Entity', 25, ['field' => 'value'])`.
- Declarative workflow state fixtures via `$this->seedWorkflow('Entity', 10, 'approved')`.
- Realistic test data synthesis via `$this->fake('email')`, `$this->fake('name')`, etc.
- Safe table truncation via `$this->truncate('table_name')`.
- Detailed execution logging captured via `$this->log()`.

#### Examples:
```bash
# Interactive prompt for seeder name (defaults to 'default' app)
php spp.php make:seeder

# Scaffold UserSeeder for school1 app
php spp.php make:seeder UserSeeder --app=school1

# Suffix 'Seeder' is added automatically if omitted
php spp.php make:seeder Course --app=school1
```

### Options Available
- `<SeederName>` (argument): Name of the seeder class to scaffold (e.g., `UserSeeder` or `Course`).
- `--app=<app_name>` (optional): Application directory under `src/` to place the seeder in (defaults to `default`).

### Under the Hood Activity
- Resolves application path `src/{app}/seeders/`.
- Creates the target directory if not present.
- Writes `src/{app}/seeders/{SeederName}Seeder.php` containing modern `SPPSeeder` fluent boilerplate.
