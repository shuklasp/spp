## pkg:install

**Purpose**: Universal Installer for SPP Applications and Modules. Downloads and installs packages from the central repository (`satyalab.net`), direct HTTP/HTTPS URLs, GitHub release assets, or local `.spppkg` / `.zip` files into an existing SPP environment on a server.

### Synopsis

```bash
php spp.php pkg:install <source_or_name> [--as=<name>] [--scope=system|app] [--app=<appName>] [--version=<semver>] [--repo=<name|url>] [--force]
```

### Extended Usage

`pkg:install` is the primary entrypoint for adding applications and extensions to an SPP server:

1. **Install an Application from the Central Registry**:
   Resolves the latest package from `satyalab.net`, downloads it, validates SHA-256 integrity, extracts assets, and registers the app:
   ```bash
   php spp.php pkg:install satyalab/school1
   ```

2. **Install with Custom Name**:
   Install an app under a different tenant identifier:
   ```bash
   php spp.php pkg:install satyalab/school1 --as=campus_north
   ```

3. **Install a Framework-Wide System Module**:
   Installs an extension into `spp/modules/optional` and registers it in `etc/modules.yml`:
   ```bash
   php spp.php pkg:install sppmail --scope=system
   ```

4. **Install a Tenant App-Level Module**:
   Installs an extension scoped strictly to an existing tenant application in `src/{app}/modules`:
   ```bash
   php spp.php pkg:install grading_tools --scope=app --app=school1
   ```

5. **Install from a Direct URL, GitHub Asset, or Local File**:
   ```bash
   php spp.php pkg:install https://repo.satyalab.net/dist/apps/school1-1.0.0.spppkg
   php spp.php pkg:install dist/packages/school1-1.0.0.spppkg
   ```

### Options Available

| Option | Description | Default |
|---|---|---|
| `<source_or_name>` | Registry package name, direct HTTP/HTTPS URL, or local file path. | Required |
| `--as=<name>` | Override destination application or module identifier. | Package name |
| `--scope=system\|app` | Module installation scope: `system` (framework-wide) or `app` (tenant-scoped). | Auto-detected |
| `--app=<appName>` | Target application identifier when installing an app-level module. | - |
| `--version=<semver>` | Target a specific package release version from the repository. | Latest release |
| `--repo=<name\|url>` | Repository identifier from `etc/pkg-repositories.yml` or custom URL. | `'satyalab'` |
| `--force` | Overwrite existing directory if installation target already exists. | `false` |

### Under the Hood Activity

1. **Package Resolution & Download**: Resolves package source from registry, CDN, GitHub, or local disk, downloading to `var/cache/packages/`.
2. **Cryptographic Integrity Check**: Compares downloaded file's SHA-256 hash against the repository manifest.
3. **Extraction & Provisioning**:
   - **For Apps**: Extracts `src/` to `src/{app}`, `etc/` to `etc/apps/{app}`, `resources/` to `resources/{app}`, and `public/` to `public/{app}`. Automatically registers app in `etc/global-settings.yml` and executes database setup.
   - **For System Modules**: Extracts to `spp/modules/optional/{mod}`, activates in `etc/modules.yml`, and triggers `ModuleInstaller::setModuleStatus()`.
   - **For App Modules**: Extracts to `src/{app}/modules/{mod}` and activates in `etc/apps/{app}/modules.yml`.
