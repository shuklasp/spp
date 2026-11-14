## `issue` (Alias for `sppdocs:issue`)

**Purpose**: Manage SPPDocs project issues, tasks, and bugs directly from the command line interface.

### Synopsis
```bash
php spp.php issue [action] [options]
php spp.php sppdocs:issue [action] [options]
```

### Extended Usage
`issue` is the shorthand alias for `sppdocs:issue`. For complete usage and technical details, see the official manual:
[sppdocs-issue.md](file:///c:/projects/apache/school1/docs/commands/sppdocs-issue.md).

#### Practical Examples
```bash
# List all open issues
php spp.php issue list --project=demo-app --status=open

# Create a task
php spp.php issue create --project=demo-app --title="Update API docs" --priority=medium

# Close an issue
php spp.php issue close --project=demo-app --id=issue_1712345678_abcd
```

### Options Available
- `action`: `create`, `list`, `view`, `close` (default: `list`)
- `--project=<id>`: Project key (default: `demo-app`)
- `--title="<text>"`: Issue title (required for create)
- `--desc="<text>"`: Issue description
- `--type=<type>`: `task`, `bug`, `feature`, `epic` (default: `task`)
- `--priority=<p>`: `low`, `medium`, `high`, `urgent` (default: `medium`)
- `--status=<s>`: `open`, `closed`, `all` (default: `open`)
- `--id=<id>`: Target issue ID

### Under the Hood Activity
- Dispatches through `StorageFactory` to active project storage driver (SQLite or JSON).
- Logs events to project activity audit trail via `AuditLogService`.
- SAPI guarded: `isCLIOnly(): bool` returns `true`.
