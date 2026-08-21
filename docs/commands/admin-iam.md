## `admin:iam`

**Purpose**: Manage Admin IAM operations. Usage: admin:iam <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:iam [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, \SPPMod\SPPAuth\SPPGroup, secret.

