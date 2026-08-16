## `deploy:push`

**Purpose**: Push the local project state to a remote SPP target server

### Synopsis
```bash
php spp.php deploy:push [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--artifact=` : Expects a value. Extracted via static analysis.
- `--dry-run` : Boolean flag or option. Extracted via static analysis.
- `--no-db` : Boolean flag or option. Extracted via static analysis.
- `--no-files` : Boolean flag or option. Extracted via static analysis.
- `--force` : Boolean flag or option. Extracted via static analysis.
- `--pre_deploy` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--message` : Boolean flag or option. Extracted via static analysis.
- `--keys` : Boolean flag or option. Extracted via static analysis.
- `--debug` : Boolean flag or option. Extracted via static analysis.
- `--sql` : Boolean flag or option. Extracted via static analysis.
- `--Create Table` : Boolean flag or option. Extracted via static analysis.
- `--webhooks` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDeploy\Scanner\ProjectScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \ZipArchive, \SPPMod\SPPDB\SPPDB.

