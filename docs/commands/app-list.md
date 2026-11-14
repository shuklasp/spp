## app:list

**Purpose**: List all registered applications, distinguishing user-space applications from protected system portals (`sppadmin`, `sppdev`, `default`), along with their base URLs, database prefixes, Dev Mode status, and integrated sub-packages.

### Synopsis
```bash
php spp.php app:list
```

### Extended Usage
The `app:list` command audits all active applications registered in the global configuration and discoverable in `src/`.
- Identifies **System Applications** (`sppadmin`, `sppdev`, `default`) with the `system` type and shows their active Dev Mode status.
- Discovers integrated packages under application roots (e.g. `SPPDocs` packages: `cms`, `docs`, `erp`, `infra`, `lekhak`, `lms`, `pm`, `social`) and displays package counts instead of polluting the root application registry.

```bash
php spp.php app:list
```

### Options Available
No additional parameters required.

### Under the Hood Activity
- Scans `spp/etc/global-settings.yml` for registered application metadata and operational profile.
- Inspects `src/*/packages/` for modular component packages.
- Formats and displays an aligned console status table.
