# SPP Generic Distribution Configuration Templates (`etc.dist/`)

This directory contains pristine, distribution-ready generic configuration templates for the Satya Portal Pack (SPP) framework.

## Architecture & Lifecycle

1. **Isolation from Live Deployments**:
   - Files in `etc.dist/` are generic defaults shipped with framework updates.
   - Live effective configurations reside in `etc/` (and `spp/etc/`).
   - Deployment updates (`deploy:push`) **NEVER** overwrite existing files in `etc/` or `spp/etc/`.

2. **Copy on Demand**:
   - When the framework boots or when an administrative module queries a configuration (e.g. `App::getGlobalSettings()`, `DomainRouter::loadConfig()`, `DnsProviderManager`), the `ConfigIsolationManager` inspects the effective config directory (`etc/`).
   - If an effective configuration file does **not** exist yet, it is automatically copied from `etc.dist/<filename>` into `etc/<filename>`.
   - If the effective configuration already exists, it is **never** touched or overwritten.

3. **Manual Seeding / Initialization**:
   - You can inspect and seed missing configuration files at any time via:
     ```bash
     php spp.php config:init
     ```
