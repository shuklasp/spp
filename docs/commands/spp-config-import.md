# NAME
spp config:import - Import database tables and settings from an exported file

# SYNOPSIS
`php spp.php config:import <file> [--on-conflict=drop|merge|abort]`

# PURPOSE
Reverses `config:export`, consuming an SQL, SQLite, or XDB payload file to hydrate the connected database and restore framework configurations safely.

# OPTIONS AVAILABLE
- `<file>` : Path to the export payload file.
- `--on-conflict=<strategy>` : Handling strategy when tables have data:
  - `drop` : Nuke existing tables entirely.
  - `merge` : Use `INSERT IGNORE` to skip conflicts.
  - `abort` : Halt execution completely.
  - `prompt` : Interact with the user (Default).

# UNDER THE HOOD ACTIVITY
1. **Format Detection & Validation:** Identifies file structure. Queries target DB to identify collision risks. Resolves conflict strategies automatically or via CLI prompts.
2. **Settings Restoration:** Detects embedded YAML config data. Safely backs up the existing `etc/global-settings.yml` utilizing a `.bak` timestamp, before replacing it.
3. **SQL Import:** Modifies syntax dynamically in memory if `--on-conflict=merge` is set (changing `INSERT` to `INSERT IGNORE` and stripping `DROP`). Slices statements on semicolons and executes them.
4. **SQLite Import:** Connects via PDO. Traverses the embedded schema, translates schemas dynamically back to MySQL syntax if needed, and uses prepared statements to push payloads row by row.
5. **XDB Import:** Parses the XML DOM, translates nodes and `null` attributes into prepared parameterized bindings for safe bulk insertion.

# EXAMPLES
Merge an exported SQLite payload:
`php spp.php config:import var/exports/export_2026.sqlite --on-conflict=merge`
