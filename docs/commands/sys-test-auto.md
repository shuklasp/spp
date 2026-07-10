## `sys:test:auto`

**Description**: Runs Automated Evolutionary Testing (Parikshak) for the current application.

### Synopsis
```bash
php spp.php sys:test:auto [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB, Parikshak.

