## `make:view`

**Purpose**: Create a new view definition (equivalent to Drupal Views).

### Synopsis
```bash
php spp.php make:view [OPTIONS]
```

### Options Available
- `--table=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: view, \SPPMod\SPPDB\SPPDB.

