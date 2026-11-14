## `make:app`

**Purpose**: Scaffold a new isolated SPP application context with full directory architecture, configuration, routing, workflows, and frontend templates.

### Synopsis
```bash
php spp.php make:app [app_name] [app_type] [base_url] [table_prefix] [--enterprise]
```

### Extended Usage
The `make:app` command initializes a completely independent application namespace within the SPP repository under `src/<AppName>/`, `etc/apps/<AppName>/`, and `resources/<AppName>/`.

#### Supported Application Modes:
1. **barebones** *(Default & Recommended for New Developers)*:
   - Creates a clean, minimalist application structure with a single, high-fidelity dark-saffron home view (`home.blade.php`).
   - Contains an interactive, step-by-step developer guide embedded directly on the page, explaining:
     - Step 1: Visual Page Design in Drishyam Studio.
     - Step 2: Modeling Database Entities (`SPPEntity`).
     - Step 3: Orchestrating State Machines & Workflows (`WorkflowManager`).
     - Step 4: Modular Partials with Zero Inline HTML (`@spppartial`).
     - Step 5: Native Parikshak Automated Testing (`SPPTestCase`).
     - Step 6: CLI Command Center & Scaffolding.
2. **mixed**:
   - Flagship showcase application combining SPP-UX reactive components, Blade views, raw PHP pages, and a headless REST API.
3. **sppux**:
   - Single Page Application (SPA) utilizing SPP-UX reactive component stores and virtual DOM.
4. **blade**:
   - Traditional server-rendered application powered by Laravel Blade templates and ViewControllers.
5. **native**:
   - Native PHP pages augmented with ViewPage pipeline and server-side components.
6. **api**:
   - Headless REST API backend with JSON endpoints and authentication middleware.
7. **dropin**:
   - Rapid prototyping mode with drop-in HTML/PHP files and YAML form processing.

#### Examples:
```bash
# Interactive mode (prompts for name and preset type):
php spp.php make:app

# Scaffold a barebones starter app for novice developers:
php spp.php make:app MyClinic barebones /clinic clinic_

# Scaffold a full enterprise showcase app with Redis cache/session:
php spp.php make:app Store mixed /store store_ --enterprise
```

### Options Available
- `app_name` : The PascalCase or CamelCase name of the application (e.g., `HospitalManagement`, `StoreApp`).
- `app_type` : The application architecture template. Values: `barebones`, `mixed`, `sppux`, `blade`, `native`, `api`, `dropin`. Default: `barebones`.
- `base_url` : The base URI segment where the application will be routed (e.g., `/clinic`). Default: `/<lowercase_app_name>`.
- `table_prefix` : Database table prefix for entity isolation (e.g., `clinic_`). Default: `<app_name>_`.
- `--enterprise` : Enables enterprise features including Redis caching and session handlers. Default: `false`.

### Under the Hood Activity
1. **Filesystem Writes**:
   - Creates directory structure under `src/<AppName>/`, `etc/apps/<AppName>/`, and `resources/<AppName>/views/`.
   - Generates default workflow configuration in `etc/apps/<AppName>/workflows/default.yml`.
   - Updates `spp/etc/global-settings.yml` to register the new application's routing and paths.
   - Generates home view template (`home.blade.php`), layout, and starter controller.
   - Writes `__spp_file_props.yml` metadata manifests across generated directories.
2. **Database Interactions**:
   - If the `sppdb` module is enabled, triggers `php spp.php migrate --app=<AppName>` to run initial schema migrations.
3. **Security & SAPI Guard**:
   - Strictly CLI-only (`isCLIOnly() === true`), preventing execution through web requests.
