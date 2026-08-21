## make:live-component
**Purpose**: Scaffolds a new Live Component class and its associated external HTML partial.

### Synopsis
```bash
php spp.php make:live-component <ComponentName> [--app=AppName]
```

### Extended Usage
This command generates a pure PHP backend Live Component designed to work with the `SPPLive` and `SPP-UX` full-stack morphing engine. The generated component logic resides in PHP, while the view template is strictly separated into an external HTML partial to enforce zero inline HTML string literals.

### Options Available
- `<ComponentName>`: (Required) The class name of the Live Component (e.g., `DashboardMetrics`).
- `--app=<AppName>`: (Optional) The context application to scaffold into. Defaults to the primary `spp/` context if omitted.

### Under the Hood Activity
- Creates a new PHP class at `src/{AppName}/live/class.{componentname}.php`.
- Creates an external HTML partial at `resources/{AppName}/views/partials/{ComponentName}.html`.
