## env:mode

**Purpose**: Switch environment profile between development (`dev`) and production (`prod`), toggling error reporting and enabling/disabling system administration and developer portals (`sppadmin`, `sppdev`).

### Synopsis
```bash
php spp.php env:mode <dev|prod>
```

### Extended Usage
The `env:mode` command switches the operational environment mode across the framework.
- In **`dev` mode**:
  - Sets `profile: dev` in `spp/etc/global-settings.yml` and `<profile>dev</profile>` in `spp/etc/settings.xml`.
  - Enables `SPP_DEBUG=true` (Ignition-style developer error pages and diagnostic flight recorder).
  - Enables access to protected internal system portals (`sppadmin`, `sppdev`).
- In **`prod` mode**:
  - Sets `profile: prod` in `spp/etc/global-settings.yml` and `<profile>production</profile>` in `spp/etc/settings.xml`.
  - Enables `SPP_DEBUG=false` (clean user-facing 500 error pages).
  - Disables access to protected internal system portals (`sppadmin`, `sppdev`), responding with `403 Forbidden` for security.
- Clears the compiled configuration cache (`var/cache/system/config.php`).

```bash
# Enable dev mode and internal portals
php spp.php env:mode dev

# Lock down server into production mode
php spp.php env:mode prod
```

### Options Available
- `<dev|prod>`: Target environment profile mode. Required.
  - `dev`: Developer profile with active diagnostic portals and debug stack traces.
  - `prod`: Production profile with disabled diagnostic portals and hardened error pages.

### Under the Hood Activity
- Atomically updates `profile` and `debug` in `spp/etc/global-settings.yml`.
- Updates `spp/etc/settings.xml` profile node.
- Invalidates and removes compiled system configuration cache `var/cache/system/config.php`.
