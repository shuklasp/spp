## make:report

**Purpose**: Scaffold an enterprise declarative SPPReport YAML configuration and external report presentation partial.

### Synopsis
```bash
php spp.php make:report <name> [--app=appname]
```

### Extended Usage
The `make:report` command scaffolds a complete reporting definition and presentation partial for business intelligence, OLAP pivot tables, and tabular data exports:

```bash
# Generate users_kpi report in default application context
php spp.php make:report users_kpi

# Generate monthly_sales report in the 'school' app context
php spp.php make:report monthly_sales --app=school
```

Generated reports support:
- Secure multi-engine SQL query compilation across SQLite, MySQL, PostgreSQL, and XDB.
- Aggregated functions: `COUNT`, `SUM`, `AVG`, `MIN`, `MAX`.
- Parameterized boolean filter conditions.
- Direct integration with `$this->report('<name>')` and `$this->reportKpi('<name>')` in `ViewController` and `ResourceController`.
- Direct Blade rendering via `@sppreport('<name>')`, `@sppreport_kpi('<name>')`, `@sppreport_chart('<name>')`, and `@sppreport_pivot('<name>')`.
- Interactive report toolbar: real-time live search, clickable column sorting, and instant clipboard TSV copy.
- Multi-format exports: CSV, Excel (XLS), and PDF.

### Options Available
- `<name>`: Unique identifier for the report (e.g. `user_activity`, `annual_revenue`).
- `--app=<appname>`: (Optional) Target application context. Defaults to active context or `default`.

### Under the Hood Activity
- Creates `etc/apps/<appname>/reports/<name>.yml` with parameterized filters and tutorial comments.
- Generates a standalone external Blade partial at `src/<appname>/pages/partials/reports/<name>.blade.php` adhering to the Zero Inline HTML rule.
- Registers report in the SPPReport registry and Developer Studio BI catalog.
