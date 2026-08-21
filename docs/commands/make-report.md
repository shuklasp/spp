## make:report

**Purpose**: Scaffolds a new SPPReport YAML configuration and its corresponding HTMX view partial securely.

### Synopsis

```bash
php spp.php make:report <Name>
```

### Extended Usage

The `make:report` command helps developers rapidly scaffold new Enterprise reports. Rather than writing JSON or YAML manually, this command provisions a standard configuration with safe defaults inside the `etc/sppreports/` directory. Furthermore, to adhere to the strict framework architectural rules regarding zero inline HTML, it generates a securely escaped view partial in the `partials/reports/` directory that uses `htmlspecialchars()` and avoids executing PHP directly inside the controller.

### Options Available

- `<Name>`: (Required) The name of the report you wish to scaffold. It will be sanitized to letters, numbers, hyphens, and underscores.

### Under the Hood Activity

1. Reads the provided `<Name>` and sanitizes it (e.g. `daily_sales`).
2. Creates `etc/sppreports/<name>.yml` with boilerplate structure for querying the `users` table.
3. Creates `partials/reports/<name>.php` with a standard HTML grid wrapped safely for HTMX and Turbo Streams.
