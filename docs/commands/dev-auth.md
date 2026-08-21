## `dev:auth`

**Purpose**: Manage Dev Auth operations. Usage: admin:auth <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:auth [OPTIONS]
```

### Options Available
- `--spp_dev_fallback` : Boolean flag or option. Extracted via static analysis.
- `--spp_dev_user` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPAuth\SPPUser, \SPPMod\SPPDB\SPPDB.

