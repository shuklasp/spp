# NAME
`make:blade-project` - Scaffold a new Blade-enabled SPP application

# SYNOPSIS
`php spp.php make:blade-project <app_name> [--force]`

# PURPOSE
The `make:blade-project` command creates an entirely new SPP application context heavily optimized and pre-configured for the SPP Blade template engine. This is ideal for monolithic enterprise applications favoring server-side rendering over client-side reactive models.

# OPTIONS AVAILABLE
- `<app_name>` (string, required): The namespace and directory path name of the new project context.
- `--force` (flag, optional): If specified, bypasses the directory emptiness check and forcibly overwrites or augments existing files in the app directory.

# UNDER THE HOOD ACTIVITY
The command provisions a full context lifecycle:
1. **Context Creation**: It generates standard `etc/apps/{app_name}` directories including `modsconf/sppblade`, `data`, `logs`, and `forms`.
2. **SPPBlade Module Configuration**: It explicitly writes an SPPBlade `config.yml` module configuration pointing to `resources/{app_name}/views` and sets cache pathways to `var/cache/{app_name}/blade`. It configures BladeOne to `MODE_AUTO` (0).
3. **YAML Form Scaffold**: Creates an enterprise-grade `login.yml` form incorporating internal validation models (e.g. `SPPRequiredValidator`) and structured control architectures (`SPPText`, `SPPPassword`, `SPPSubmit`).
4. **Layout Generation**: It outputs a high-fidelity "glassmorphism" `app.blade.php` master layout, which includes complex CSS variables, multi-theme (dark/light) dataset toggles, and responsive grids. 
5. **View Orchestration**: An `index.blade.php` is created showcasing SPP directive usages like `@@sppform`, `@@sppauth`, and `@@sppbind`.
6. **Entry Script Writing**: It compiles a functional `index.php` PHP entry point that maps routes, intercepts logout parameters, mocks an authentication handler to process the YAML form, and instructs `\SPPMod\SPPView\ViewPage::processForms()` before yielding control to the Blade rendering engine.
7. **System Injection**: Automatically alters `spp/etc/global-settings.yml` to inject the new app's routing configuration and builds a `pages.yml`.

# EXAMPLES
**1. Scaffold a new blade project called 'portal':**
```bash
php spp.php make:blade-project portal
```
