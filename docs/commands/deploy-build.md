## `deploy:build`

**Purpose**: Create a local deployment artifact bundle without pushing

### Synopsis
```bash
php spp.php deploy:build [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:build <target_uri> [--key=YOUR_API_KEY] [--no-db] [--no-files]

```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--no-db` : Boolean flag or option. Extracted via static analysis.
- `--no-files` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--sql` : Boolean flag or option. Extracted via static analysis.
- `--Create Table` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDeploy\Scanner\ProjectScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \ZipArchive, \SPPMod\SPPDB\SPPDB.

