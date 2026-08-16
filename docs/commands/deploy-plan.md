## `deploy:plan`

**Purpose**: Perform a dry run to view file changes and raw database SQL diffs before deploying

### Synopsis
```bash
php spp.php deploy:plan [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:plan <target_uri> [--key=YOUR_API_KEY] [--no-db]

```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--no-db` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--sql` : Boolean flag or option. Extracted via static analysis.
- `--Create Table` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDeploy\Scanner\FileScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \SPPMod\SPPDB\SPPDB.

