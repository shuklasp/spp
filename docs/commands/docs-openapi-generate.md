## docs:openapi:generate

**Purpose**: Generate an automated OpenAPI 3.1 specification schema from SPPEntity configurations and Controllers across the application.

### Synopsis

```bash
php spp.php docs:openapi:generate [--output=<path>] [--title=<title>] [--version=<version>]
```

### Extended Usage

The `docs:openapi:generate` command scans all YAML entity definitions in the application source directory (`src/entities/*.yml`) and inspects public Controller actions using PHP reflection. It converts these definitions into a fully compliant OpenAPI 3.1 schema JSON file, suitable for import into Postman, Swagger UI, ReDoc, or any OpenAPI compatible client.

Example:
```bash
php spp.php docs:openapi:generate --output=docs/api/v1/openapi.json --title="School Core API" --version=1.1.0
```

### Options Available

- `--output=<path>`: File path where the generated JSON schema will be written (defaults to `docs/openapi.json`).
- `--title=<title>`: Title of the API specification (defaults to `SPP Automated API Specification`).
- `--version=<version>`: Semantic version string of the API specification (defaults to `1.0.0`).

### Under the Hood Activity

1. **Filesystem Reads**: Scans `src/entities/*.yml` and `src/controllers/*.php` to discover attributes, table properties, and public controller methods.
2. **Reflection & Parsing**: Uses Symfony Yaml parser for entity files and `\ReflectionClass` for controller classes to determine endpoints, methods, parameters, request bodies, and responses.
3. **Filesystem Writes**: Creates the destination directory if missing and writes the formatted JSON schema to the specified `--output` file path.
4. **No Database Interaction**: Inspects schema definitions and classes statically without requiring live database connections or queries.
