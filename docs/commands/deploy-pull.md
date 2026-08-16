## `deploy:pull`

**Purpose**: 

### Synopsis
```bash
php spp.php deploy:pull [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--force` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--debug` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \ZipArchive, \SPPMod\SPPDB\SPPDB.

