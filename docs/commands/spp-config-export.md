# NAME
spp config:export - Export database tables and global settings

# SYNOPSIS
`php spp.php config:export [--format=sql|sqlite|xdb] [--tables=t1,t2,...] [--xdb-name=mydb]`

# PURPOSE
A robust utility that extracts the entirety (or a subset) of the configured database's schema and records, alongside framework YAML settings, into an offline payload file (SQL dump, SQLite DB, or XDB XML).

# OPTIONS AVAILABLE
- `--format=<type>` : Export format: `sql`, `sqlite`, or `xdb`. Defaults to `sql`.
- `--tables=<list>` : Comma-separated list of exact table names to export.
- `--xdb-name=<name>` : Internal database name identifier when exporting to `xdb`.

# UNDER THE HOOD ACTIVITY
1. **Preparation:** Validates `var/exports/` existence. Connects via `\SPPMod\SPPDB\SPPDB`. Discovers tables natively (`SHOW TABLES` or `sqlite_master`).
2. **Format - SQL:** Generates a monolithic `.sql` file. Iterates tables, executes `SHOW CREATE TABLE` for schema definitions, then iterates `SELECT *` generating sanitized `INSERT INTO` batches wrapping string data natively.
3. **Format - SQLite:** Instantiates `\PDO('sqlite:...')`. Reverse-engineers MySQL schemas via `DESCRIBE`, translating datatypes dynamically to SQLite specifications. Iterates result rows migrating data via prepared bound parameters.
4. **Format - XDB:** Generates a structured XML payload using `\DOMDocument`. Nests tables, rows, and columns inside an `<xdb>` root node handling CDATA text blocks.
5. **Settings Appendage:** Injects the `global-settings.yml` into the export. In SQL, as a commented block. In SQLite, within a hidden `_spp_settings` table. In XDB, within a `<settings>` node.

# EXAMPLES
Export specific tables as a portable SQLite database:
`php spp.php config:export --format=sqlite --tables=users,roles`
