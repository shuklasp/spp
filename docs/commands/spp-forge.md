# NAME

`spp forge` - Unified automation and LiveSync engine for SPP

# SYNOPSIS

`php spp.php forge [subcommand] [options]`

# PURPOSE

The `forge` command acts as the next-generation automation engine for the SPP Framework. It handles unified scaffolding of modules and components, manages module compilation and database migrations, and provides an active development watcher (LiveSync) to instantly react to filesystem changes.

# OPTIONS AVAILABLE

- `module <name>`: Scaffolds a new SPP module.
- `component <name>`: Creates a new UX component using `MakeUXComponentCommand` logic.
- `livesync`: Starts a persistent development watcher polling for file modifications.
- `compile <app>`: Compiles the module manifest cache for a specific application context.
- `migrate`: Runs any pending migrations for all installed modules.
- `migration <mod> <ver>`: Generates a new migration class boilerplate for a specific module and version.

# UNDER THE HOOD ACTIVITY

The `ForgeCommand` acts as a facade delegating to several distinct functional areas depending on the subcommand invoked:
- **module**: When scaffolding a module, it creates the module directory structure under `SPP_BASE_DIR/modules/spp/<name>`, injecting a `class.<name>.php` for the core class definition and a `modinit.php` for boot registration.
- **component**: Delegates entirely to `MakeUXComponentCommand` to build the required JS and PHP structures for an SPP UX component.
- **livesync**: Initiates an infinite loop using `usleep(500000)` (polling twice a second) that recursively scans `SPP_BASE_DIR/modules`, `SPP_BASE_DIR/apps`, and `src` directories using `RecursiveDirectoryIterator` to locate the highest `getMTime()`. If the modification time changes, it updates the `.livesync` sentinel file with the new timestamp. The frontend clients can poll this sentinel file to hot-reload.
- **compile**: Triggers `\SPP\Core\ModuleCompiler` initialized with the app context (defaulting to current context). The compiler generates an optimized array of modules and their paths saved in a cached file.
- **migrate & migration**: For `migration`, it creates a `Migration_X_Y_Z.php` file inside the target module's `migrations` folder with boilerplate `up()` and `down()` methods. For `migrate`, it initializes a `VersionManager` and inspects the compiled module cache. It compares the registered manifest version against the installed version. If an upgrade is needed, it sorts all files in the module's `migrations` directory, requiring and executing the `up()` method of any migration whose version lies between the currently installed version and the target manifest version. Once successful, `VersionManager->updateVersion()` is called to mark the migration complete.

# EXAMPLES

**Create a new module:**
```bash
php spp.php forge module Blog
```

**Generate a database migration for a module:**
```bash
php spp.php forge migration SppDb 1.2.0
```

**Start the LiveSync development watcher:**
```bash
php spp.php forge livesync
```

**Run all pending module migrations:**
```bash
php spp.php forge migrate
```
