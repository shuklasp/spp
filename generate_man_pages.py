import os

man_pages = {
    "dbsettings-export.md": r"""# dbsettings:export

## NAME
dbsettings:export - Export SPP module DB settings to JSON

## SYNOPSIS
`php spp.php dbsettings:export [--app=<app_name>]`

## PURPOSE
Exports the currently configured database settings mapped by the `DBSettings` module into a structured JSON file format for backups or environment migrations.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Specify the SPP application context (default: 'default').

## UNDER THE HOOD ACTIVITY
The command extracts the application context from the `--app` argument. It then attempts to invoke `\SPP\Scheduler::withContext()` to set the runtime context. Within the closure, it checks whether the `\SPPMod\DBSettings\DBSettings` class is loaded and available. Currently, the actual export logic is a stub and outputs a "Implementation pending" message if the module is active.

## EXAMPLES
```bash
php spp.php dbsettings:export --app=admin
```
""",
    "dbsettings-import.md": r"""# dbsettings:import

## NAME
dbsettings:import - Import SPP module DB settings from JSON

## SYNOPSIS
`php spp.php dbsettings:import --file=<settings.json> [--app=<app_name>]`

## PURPOSE
Imports database configuration overrides and parameters directly from a JSON source file into the SPP DB Settings registry.

## OPTIONS AVAILABLE
- `--file=<path>`: **Required**. Path to the JSON file containing the exported database settings.
- `--app=<app_name>`: Specify the SPP application context (default: 'default').

## UNDER THE HOOD ACTIVITY
The command validates the presence of the `--file` flag. It shifts the application context via `\SPP\Scheduler::withContext()`. Like the export command, it verifies the existence of the `\SPPMod\DBSettings\DBSettings` module. If found, it indicates that the underlying core logic for consuming the JSON file is still an unimplemented stub.

## EXAMPLES
```bash
php spp.php dbsettings:import --file=settings_backup.json --app=default
```
""",
    "db-sync.md": r"""# db:sync

## NAME
db:sync - Synchronize data between two database adapters

## SYNOPSIS
`php spp.php db:sync --from=[engine:table] --to=[engine:table]`

## PURPOSE
A CLI utility to incrementally extract, transform, and load (ETL) data between different database engines (e.g., from MySQL to XDB).

## OPTIONS AVAILABLE
- `--from=[engine:table]`: **Required**. The source database engine and table name separated by a colon.
- `--to=[engine:table]`: **Required**. The target database engine and table name separated by a colon.

## UNDER THE HOOD ACTIVITY
The script parses the `--from` and `--to` arguments to resolve the respective engine names and table designations. It initializes two separate `\SPPMod\SPPDB\SPPDB` adapter objects: a source initialized natively and a target provisioned via a custom DSN (`[engine]:dbname=default`). It extracts all records from the source table using `SELECT *`. Then, it dynamically pulls the column definitions via `getSchema()` to provision the target table using `createTableIncremental()`. Finally, it iterates over the result set, calling `insertValues()` row-by-row on the target connection.

## EXAMPLES
```bash
php spp.php db:sync --from=mysql:users --to=xdb:users_backup
```
""",
    "db-verify.md": r"""# db:verify

## NAME
db:verify - Runs the SPP XDB MySQL Compatibility Verification Suite

## SYNOPSIS
`php spp.php db:verify`

## PURPOSE
Executes the SPP XDB MySQL compatibility check script to ensure the SPPDB abstraction layers handle cross-engine translations without syntax faults or connection errors.

## OPTIONS AVAILABLE
This command does not accept additional options.

## UNDER THE HOOD ACTIVITY
When triggered, the command looks for a specific test suite file located at `spp/modules/spp/sppxdb/test_mysql_compatibility.php`. If the file exists, it uses the native PHP `passthru()` function to spawn a sub-process running the script directly. This offloads the heavy functional testing directly to the test harness and relays the real-time stdout feedback to the user's terminal.

## EXAMPLES
```bash
php spp.php db:verify
```
""",
    "sys-debug.md": r"""# sys:debug

## NAME
sys:debug - Toggle global framework debug mode (on|off)

## SYNOPSIS
`php spp.php sys:debug <on|off>`

## PURPOSE
Enables or disables verbose diagnostics and the API Flight Recorder globally across the SPP framework.

## OPTIONS AVAILABLE
- `on`: Enable debug mode.
- `off`: Disable debug mode.

## UNDER THE HOOD ACTIVITY
The command validates the desired state argument (`on` or `off`). It locates the `global-settings.yml` configuration file inside `SPP_ETC_DIR`. It utilizes the `Symfony\Component\Yaml\Yaml` component to parse the YAML document into a PHP associative array. It navigates to the `['settings']['debug']` key, forcefully creating the `['settings']` array block if absent, and assigns a boolean derived from the command input. It then re-dumps the array structure into YAML format and performs a `file_put_contents` flush to disk to solidify the change.

## EXAMPLES
```bash
php spp.php sys:debug on
```
""",
    "delete-app.md": r"""# delete:app

## NAME
delete:app - Delete an SPP application context and all its data

## SYNOPSIS
`php spp.php delete:app [AppNameToConfirm] [--force]`

## PURPOSE
Completely removes an existing SPP application, wiping its configuration, source code, and resource directories from the system.

## OPTIONS AVAILABLE
- `AppNameToConfirm`: The name of the application context to delete. Prompts interactively if omitted.
- `--force`: Bypass the standard "(y/N)" confirmation prompt.

## UNDER THE HOOD ACTIVITY
The command starts by validating the target app name, actively preventing deletion of protected system apps like `default` or `admin`. It requests manual confirmation via `prompt()` if `--force` is absent. It then systematically performs a recursive deletion of three specific directories using standard filesystem scanning (`scandir`, `unlink`, `rmdir`): `etc/apps/{appName}`, `src/{appName}`, and `resources/{appName}`. To complete the operation, it opens the master `spp/etc/global-settings.yml` registry with the Symfony YAML component, unsets the application entry under the `['apps']` node, and flushes the modified registry back to the disk.

## EXAMPLES
```bash
php spp.php delete:app storefront --force
```
""",
    "di-list.md": r"""# di:list

## NAME
di:list - List the Dependency Injection container bindings

## SYNOPSIS
`php spp.php di:list [--app=<app_name>]`

## PURPOSE
Provides a diagnostic listing of all active bindings and singletons present inside the SPP Dependency Injection container.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Context boundary for evaluating the DI container (default: 'default').

## UNDER THE HOOD ACTIVITY
The command bootstraps into the requested application context using `\SPP\Scheduler::withContext()`. It retrieves the core application container via `\SPP\App::getApp()->getContainer()`. Due to internal visibility, it leverages PHP's `\ReflectionClass` to bypass encapsulation and extract the protected `bindings` and `instances` properties. It loops over the `bindings` array, inspecting whether each binding is mapped as a Closure or class name, classifying them as Singletons (shared) or Factories. It then merges this output with directly resolved `instances` that bypass strict initial bindings, outputting a cleanly padded, columnar table.

## EXAMPLES
```bash
php spp.php di:list
```
""",
    "diff-apply.md": r"""# diff:apply

## NAME
diff:apply - Apply a patch or delta file

## SYNOPSIS
`php spp.php diff:apply [--file=<patch.json>]`

## PURPOSE
Applies a generic JSON delta patch to the underlying state engine.

## OPTIONS AVAILABLE
- `--file=<patch.json>`: Target delta file to apply.

## UNDER THE HOOD ACTIVITY
The command checks the system for the presence of the `\SPPMod\SPPDiff\DeltaEngine` module class. Currently acting as a stub, it asserts module availability and indicates command usage, reserving full delta assimilation logic for future module iterations.

## EXAMPLES
```bash
php spp.php diff:apply --file=patch.json
```
""",
    "diff-compare.md": r"""# diff:compare

## NAME
diff:compare - Compare two JSON arrays or states

## SYNOPSIS
`php spp.php diff:compare`

## PURPOSE
Compares two datasets or serialized states using the DeltaEngine component.

## OPTIONS AVAILABLE
This command requires external/custom integration to feed JSON files.

## UNDER THE HOOD ACTIVITY
It assesses the presence of `\SPPMod\SPPDiff\DeltaEngine`. It prints a confirmation if the module is active and exits, currently serving as a placeholder stub that relies on custom programmatic hooks rather than direct CLI file handling.

## EXAMPLES
```bash
php spp.php diff:compare
```
""",
    "diff-history.md": r"""# diff:history

## NAME
diff:history - View revision history of an entity

## SYNOPSIS
`php spp.php diff:history --type=<ModelClass> --id=<ID>`

## PURPOSE
Pulls the complete list of chronological revisions logged by the SPPDiff RevisionManager for a given Entity instance.

## OPTIONS AVAILABLE
- `--type=<ModelClass>`: **Required**. The fully qualified class name of the entity.
- `--id=<ID>`: **Required**. The primary key ID of the entity.

## UNDER THE HOOD ACTIVITY
The command verifies that the passed ModelClass exists. It invokes the static `find_one()` method on the entity class to query the active instance from the database using the provided ID. If found, it delegates the entity to `\SPPMod\SPPDiff\RevisionManager::getHistory($entity)`. It iterates through the chronological revision payload returning metadata such as the Revision ID, creation timestamp, and the responsible User ID.

## EXAMPLES
```bash
php spp.php diff:history --type="\App\Entities\User" --id=42
```
""",
    "diff-rollback.md": r"""# diff:rollback

## NAME
diff:rollback - Rollback an entity to a previous state

## SYNOPSIS
`php spp.php diff:rollback --type=<ModelClass> --id=<ID> --rev=<RevID>`

## PURPOSE
Reverts a specific Entity to an exact historic revision tracked by the RevisionManager.

## OPTIONS AVAILABLE
- `--type=<ModelClass>`: **Required**. The fully qualified class name of the entity.
- `--id=<ID>`: **Required**. The primary key ID of the entity.
- `--rev=<RevID>`: **Required**. The specific integer Revision ID to roll back to.

## UNDER THE HOOD ACTIVITY
The command instantiates the target ModelClass and uses `find_one()` to verify the entity's current existence. It queries `\SPPMod\SPPDiff\RevisionManager::getRevision($entity, $revId)` to synthesize an older state of the entity object based on the delta logs. Finally, it executes the entity's native `save()` method, flushing the synthesized previous state directly back to the database as the new definitive state.

## EXAMPLES
```bash
php spp.php diff:rollback --type="\App\Entities\User" --id=42 --rev=5
```
""",
    "docs-build.md": r"""# docs:build

## NAME
docs:build - Documentation utilities

## SYNOPSIS
`php spp.php docs:build`
`php spp.php docs:phpdoc`

## PURPOSE
A dual-purpose command meant to either generate native static HTML SPP documentation or run an external phpDocumentor PHAR script to document API classes.

## OPTIONS AVAILABLE
- `docs:build` (argument): Builds the native SPP documentation.
- `docs:phpdoc` (argument): Invokes the standalone phpDocumentor generator.

## UNDER THE HOOD ACTIVITY
When the `docs:build` argument is passed, the script requires the localized `DocParser.php` and `StaticGenerator.php` elements of the `SPPDoc` module. It instructs `\SPPMod\SPPDoc\StaticGenerator::build()` to generate the output files directly into `docs/sppdoc`. 
When `docs:phpdoc` is supplied, it verifies the existence of `phpDocumentor.phar` in the project root. It crafts a shell command spanning the PHP executable over the PHAR file and runs it with `passthru()`, monitoring the exact `$returnVar` code to determine terminal output success or failure.

## EXAMPLES
```bash
php spp.php docs:build
php spp.php docs:phpdoc
```
""",
    "drishyam-clear.md": r"""# drishyam:clear

## NAME
drishyam:clear - Clear the Drishyam rendering cache

## SYNOPSIS
`php spp.php drishyam:clear`

## PURPOSE
Purges all temporarily compiled view artifacts from the Drishyam template engine.

## OPTIONS AVAILABLE
This command takes no arguments.

## UNDER THE HOOD ACTIVITY
The command builds the path to the localized Drishyam cache located at `var/storage/temp/views` relative to `SPP_APP_DIR`. It utilizes `glob()` to load all internal file paths into an array, and iteratively calls the native PHP `unlink()` command on every valid file it discovers, keeping a count of successful purges to output to the developer.

## EXAMPLES
```bash
php spp.php drishyam:clear
```
""",
    "drishyam-compile.md": r"""# drishyam:compile

## NAME
drishyam:compile - Pre-compile Drishyam templates for production

## SYNOPSIS
`php spp.php drishyam:compile [--app=<app_name>]`

## PURPOSE
Actively bootstraps the Drishyam rendering engine and forces a systemic pre-warming of all templates to disk cache, minimizing production latency.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Bound application context (default: 'default').

## UNDER THE HOOD ACTIVITY
It enters the bounded app context via `Scheduler::withContext()`. It validates that the `\SPPMod\Drishyam\Drishyam` engine is available and active. It retrieves the engine's singleton instance through `getInstance()`, forces a structural bootstrap routine via `boot()`, and instructs the engine to traverse and eagerly cache all reachable templates via the `preWarm()` method.

## EXAMPLES
```bash
php spp.php drishyam:compile --app=storefront
```
""",
    "drishyam-theme-check.md": r"""# drishyam:theme:check

## NAME
drishyam:theme:check - Validate Drishyam theme assets and structure

## SYNOPSIS
`php spp.php drishyam:theme:check [--app=<app_name>]`

## PURPOSE
Analyzes registered themes loaded in the active context, verifying configuration presence and structural directory integrity.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Application context binding (default: 'default').

## UNDER THE HOOD ACTIVITY
Inside the bounded execution context, the script summons the `Drishyam` core instance and invokes `boot()`. It extracts an array of active themes via `getThemes()`. It iterates over the collection, isolating the `getPath()` of each theme. It performs discrete file system checks using `file_exists()` and `glob()` to verify the presence of `theme.yml`, `style.css`, or `*.info.yml` for validity. It further probes for an active `views/` directory inside the theme root. Output is formatted into a structural terminal block per theme.

## EXAMPLES
```bash
php spp.php drishyam:theme:check
```
""",
    "ent-edit.md": r"""# ent:edit

## NAME
ent:edit - Edit an existing SPPEntity definition

## SYNOPSIS
`php spp.php ent:edit [EntityName] [OPTIONS]`

## PURPOSE
Facilitates CLI-driven or interactive editing of Entity YAML configurations, including structural relationships, database bindings, and raw attribute mutation.

## OPTIONS AVAILABLE
- `--table=TableName`: Updates the physical database table mapped to the entity.
- `--extends=Class`: Replaces the logical parent entity class.
- `--login=true|false`: Toggles SPP Login feature support flag.
- `--add-field="name:type"`: Comma-separated list for injecting or modifying schema attributes.
- `--remove-field="name"`: Comma-separated list of attributes to purge.
- `--add-relation="Target:Type:ForeignKey:PivotTable"`: Appends relation definitions.
- `--remove-relation=index`: Removes a specific relation structure by integer array index.

## UNDER THE HOOD ACTIVITY
If no arguments are provided, it forces an interactive wizard by listing available entities from `SPPEntity::listAvailableEntities()` and taking user input via a `prompt()` wrapper. It locates the entity config via `SPPEntity::getEntityConfigFile()`. It parses the source YAML, allowing either flag-driven arrays manipulations or a deeply nested interactive CLI loop. Through CLI flags, it interprets the parameters (like auto-computing many-to-many pivot tables logic) and dynamically mutates the YAML array structure. It serializes and persists the updated state strictly via `\SPPMod\SppDb\SPPEntity::saveEntityDefinition()`.

## EXAMPLES
```bash
php spp.php ent:edit Student --table=new_students --add-field="graduation_year:int"
php spp.php ent:edit User --add-relation="Role:ManyToMany:user_id:user_role"
```
""",
    "entity-crud.md": r"""# entity:crud

## NAME
entity:crud - Manage SPP entities (list, create, edit, delete)

## SYNOPSIS
`php spp.php entity:crud [action] [options]`

## PURPOSE
An inherited element manager command to handle basic CRUD structural operations over SPP entity config files directly on the filesystem.

## OPTIONS AVAILABLE
Inherited dynamically from `BaseElementCommand`. Supports listing, generating, editing, and deleting YAML configurations.

## UNDER THE HOOD ACTIVITY
The command inherits extensive logic from `BaseElementCommand`. When run, it funnels the context into `handleCrud('entity', $args)`. It resolves the file storage path through the `getElementPath()` hook, directing file pointers to the specific `App::getApp()->getAppConfDir() . '/entities/{name}.yml'` location. `listElements()` is similarly overridden to use a bracketed `glob()` call fetching all `.yml, .yaml, .xml` structural files directly from the `entities/` subfolder.

## EXAMPLES
```bash
php spp.php entity:crud list
php spp.php entity:crud delete User
```
""",
    "env-backup.md": r"""# env:backup

## NAME
env:backup - Backup all environment configurations

## SYNOPSIS
`php spp.php env:backup`

## PURPOSE
Locates, archives, and stores system-wide and application-specific `.yml`, `.yaml`, and `.json` configuration files into a time-stamped ZIP package.

## OPTIONS AVAILABLE
No additional options are accepted.

## UNDER THE HOOD ACTIVITY
The command verifies or provisions the `var/backups` directory in `SPP_BASE_DIR`. It utilizes PHP's native `\ZipArchive` extension to open a newly minted archive appended with a `Ymd_His` date format timestamp. It sweeps the global `etc/` folder for configurations, buffering their paths. It then uses array filtering and `glob()` to recursively traverse the `SPP_APP_DIR`, entering each valid context to map internal `etc/` directories. It loops through the aggregated file array, passing each source path into `ZipArchive::addFile()`, recreating the logical tree locally within the zip structure, and flushes the archive buffer.

## EXAMPLES
```bash
php spp.php env:backup
```
""",
    "env-get.md": r"""# env:get

## NAME
env:get - Get a specific configuration variable

## SYNOPSIS
`php spp.php env:get <key> [--app=appname]`

## PURPOSE
Fetches and displays real-time compiled framework configuration values.

## OPTIONS AVAILABLE
- `<key>`: **Required**. The configuration key notation to query (e.g., `sys:debug`).
- `--app=<app_name>`: Scope the variable read to a specific app.

## UNDER THE HOOD ACTIVITY
It parses the positional CLI argument to extract the target key. It shifts context via `Scheduler::withContext()`. The heavy lifting defers to `\SPP\SPPConfig::get($key)`. The return payload is verified against `null`. If the resolved payload is a scalar primitive, it stringifies it directly. If it yields an array block or an object, it processes the structure natively using `json_encode()` with `JSON_PRETTY_PRINT` format.

## EXAMPLES
```bash
php spp.php env:get database.host --app=storefront
```
""",
    "env-list.md": r"""# env:list

## NAME
env:list - List all environment and configuration variables for an app context

## SYNOPSIS
`php spp.php env:list [--app=<app_name>]`

## PURPOSE
Spits out an alphabetically sorted, flattened table representation of every single active configuration key and value known to the SPP configuration cache.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Application scope (default: 'default').

## UNDER THE HOOD ACTIVITY
Operating within a scheduled app context, the command forces an active refresh of the configuration engine via `SPPConfig::compile($appname)` to guarantee no delta mismatch. In an advanced structural hack, the command instantiates a `\ReflectionClass` targeting `SPPConfig`, forces accessibility on the private static `getCompiledPath()` method, and invokes it to extract the raw PHP array cache location directly from disk. It physically requires the cache file, triggers `ksort()`, and truncates the stringified outputs into a beautifully padded 45-character width columnar representation.

## EXAMPLES
```bash
php spp.php env:list --app=admin
```
""",
    "env-set.md": """# env:set

## NAME
env:set - Set a specific configuration variable

## SYNOPSIS
`php spp.php env:set <key> <value> [--app=appname]`

## PURPOSE
Updates a live configuration key with a new value directly from the CLI.

## OPTIONS AVAILABLE
- `<key>`: **Required**. Target setting index (e.g., `app:key`).
- `<value>`: **Required**. The literal value to enforce.
- `--app=<app_name>`: Target context scope.

## UNDER THE HOOD ACTIVITY
It captures positional strings off the CLI string stream. Inside the bounded application context, it runs a rudimentary scalar normalization block—it explicitly intercepts the text string representations of "true", "false", and "null", type-casting them to native PHP boolean/null variants. It further passes numeric inputs through a mathematical identity translation (`$value + 0`) to derive native integers/floats over strings. The polished payload is forwarded to `\SPP\SPPConfig::set($key, $value)`, assuming the configuration layer possesses write-back persistence logic.

## EXAMPLES
```bash
php spp.php env:set system.maintenance true
```
""",
    "env-status.md": """# env:status

## NAME
env:status - Display system health and environment status

## SYNOPSIS
`php spp.php env:status [--app=<app_name>]`

## PURPOSE
A comprehensive diagnostic utility yielding immediate performance metrics, connectivity statuses, and global health checks for the active SPP configuration.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Evaluated application space.

## UNDER THE HOOD ACTIVITY
Inside the closure of the bounded context, it dynamically maps server diagnostics using PHP intrinsic functions (`PHP_VERSION`, `PHP_OS`, `ini_get('memory_limit')`). It probes database connectivity by deliberately wrapping a new instantiation of `\SPPMod\SPPDB\SPPDB()` within an output buffer (`ob_start()`) and a `try/catch` block to gracefully fail without polluting the CLI stdout stream. It uses `is_writable()` to verify filesystem permissioning. It parses `session_save_path()` (defaulting to `sys_get_temp_dir()`) and counts valid active web sessions via `glob('sess_*')`. It weighs the size of the global middleware layers from `\SPP\Registry` and reads the queue size. Finally, it scores the integrity metrics on a 100% scale and outputs the matrix.

## EXAMPLES
```bash
php spp.php env:status
```
""",
    "env-token-rotate.md": """# env:token:rotate

## NAME
env:token:rotate - Rotate the system deployment token

## SYNOPSIS
`php spp.php env:token:rotate [--app=<app_name>]`

## PURPOSE
Generates and enforces a brand new cryptographically secure hexadecimal payload into the system security store, deprecating the former deployment token.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Context boundary setting.

## UNDER THE HOOD ACTIVITY
Within the isolated context constraint, it invokes PHP's `random_bytes(16)` and wraps it in `bin2hex()` to securely forge a 32-character key. It establishes a dedicated database socket directly to the `'sys'` table space under the `'security'` cluster via `\SPPMod\SPPXDB\SPP_XDB`. It manipulates the XDB engine to formally mark the `['value']` field as natively encrypted via `setEncryptedFields()`. It issues an XPath style query (`//row[key = 'deployment_token']`) to locate an existing token payload. Relying on an upsert-style decision block, it forces an `update()` if the node is confirmed, or triggers an `insert()` of a fresh row, outputting the rotated key string.

## EXAMPLES
```bash
php spp.php env:token:rotate
```
""",
    "event-dispatch.md": """# event:dispatch

## NAME
event:dispatch - Alias for event:fire

## SYNOPSIS
`php spp.php event:dispatch --event=<event_name> [--payload=<json>]`

## PURPOSE
Triggers a programmatic global event hook across the entire framework scope. Functions identically as an alias namespace for `EventFireCommand`.

## OPTIONS AVAILABLE
- `--event=<event_name>`: Target event name.
- `--payload=<json>`: JSON formatted payload data.

## UNDER THE HOOD ACTIVITY
Inherits all class methodologies, logic, and state behavior exclusively from `EventFireCommand` by explicitly requiring its parent script location dynamically (`require_once __DIR__ . '/EventFireCommand.php'`) and extending it. It merely overrides the class-protected `$name` to `event:dispatch`.

## EXAMPLES
```bash
php spp.php event:dispatch --event=user.login
```
""",
    "event-fire.md": """# event:fire

## NAME
event:fire - Trigger a specific event manually

## SYNOPSIS
`php spp.php event:fire --event=<event_name> [--payload=<json>]`

## PURPOSE
Forces a synchronous invocation of any globally registered event hook attached to the target application context, bypassing standard controller lifecycles.

## OPTIONS AVAILABLE
- `--event=<event_name>`: **Required**. Target event string matching registered listeners.
- `--payload=<json>`: Optional string representing data to be forwarded. If valid JSON, it gets actively decoded into an associative PHP representation.
- `--app=<app_name>`: App context configuration.

## UNDER THE HOOD ACTIVITY
The script iterates the arguments to extract parameters. Noticeably, it feeds the payload parameter aggressively through `json_decode()`, falling back entirely to a raw string format on parse failure (`json_decode(...) ?? substr(...)`). Bounded inside the application's closure block, it verifies if `\SPP\Core\EventManager` is currently recognized by the autoloader. If verified, it forcibly activates `\SPP\SPPEvent::triggerHook($event, $payload)`, propagating the payload data systematically down the framework's internal subscriber execution path.

## EXAMPLES
```bash
php spp.php event:fire --event=cache.clear
php spp.php event:fire --event=email.send --payload='{"to":"test@example.com"}'
```
""",
    "event-list-listeners.md": """# event:list-listeners

## NAME
event:list-listeners - List all registered global event listeners

## SYNOPSIS
`php spp.php event:list-listeners [--app=<app_name>]`

## PURPOSE
Exposes the internal mapping of every subscribed callback bound to system events inside the application context.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Specifies which application's DI context to evaluate.

## UNDER THE HOOD ACTIVITY
Bootstrapped within the bounded app closure, the command validates if `\SPP\Core\EventManager` is included in the memory pool. Due to strict memory encapsulation, it establishes a `\ReflectionClass` context around the `EventManager`. It forcefully extracts the private `listeners` mapping property using `setAccessible(true)` and `getValue()`. It scans the resulting nested associative array matrix, identifying discrete string-based event names, and prints them out alongside an aggregate count of connected callbacks listening to that specific node.

## EXAMPLES
```bash
php spp.php event:list-listeners
```
""",
    "ext-disable.md": """# ext:disable

## NAME
ext:disable - Disable a specific extension

## SYNOPSIS
`php spp.php ext:disable <extension_name>`

## PURPOSE
Placeholder stub for explicitly disabling an installed SPP extension by its module name identifier.

## OPTIONS AVAILABLE
- `<extension_name>`: **Required**. The system identifier of the target extension.

## UNDER THE HOOD ACTIVITY
Command accepts positional extraction of the primary argument representing the module payload string. Currently constructed exclusively as a structural shell, it prints a pending implementation note and gracefully terminates execution.

## EXAMPLES
```bash
php spp.php ext:disable SppDiff
```
""",
    "ext-enable.md": """# ext:enable

## NAME
ext:enable - Enable a specific extension

## SYNOPSIS
`php spp.php ext:enable <extension_name>`

## PURPOSE
Placeholder stub for activating an SPP extension system-wide.

## OPTIONS AVAILABLE
- `<extension_name>`: **Required**. The system identifier of the target extension.

## UNDER THE HOOD ACTIVITY
Awaits positional evaluation of the extension name string array index. Outputs the targeted target node. At present, physical execution of enablement logic is unmapped, serving merely as a stub routing mechanism.

## EXAMPLES
```bash
php spp.php ext:enable SppDiff
```
""",
    "ext-install.md": """# ext:install

## NAME
ext:install - Install an extension from a zip or directory

## SYNOPSIS
`php spp.php ext:install --source=<path_or_url>`

## PURPOSE
Provision an external extension archive or directory onto the SPP extension registry.

## OPTIONS AVAILABLE
- `--source=<path_or_url>`: **Required**. Defines the raw target URI or discrete system path to the extension payload.

## UNDER THE HOOD ACTIVITY
It evaluates CLI arguments for the bounded `--source` input flag. Validates its active presence. The script represents an unfinished stub interface intended to ultimately deploy archives or git source packages to the `modules/` system domain. 

## EXAMPLES
```bash
php spp.php ext:install --source=https://example.com/ext.zip
```
""",
    "ext-list.md": """# ext:list

## NAME
ext:list - List all available and installed extensions

## SYNOPSIS
`php spp.php ext:list`

## PURPOSE
Displays all discrete module packages physically present inside the system's extension ecosystem.

## OPTIONS AVAILABLE
This command requires no additional options.

## UNDER THE HOOD ACTIVITY
It defines the path to the physical system module footprint located at `SPP_BASE_DIR . '/modules'`. It verifies active directory presence before using a specialized `glob($extDir . '/*', GLOB_ONLYDIR)` search to locate discrete folders exclusively, completely ignoring loose files. It iterates over the results, formatting the raw directory basename strings, appending a static "(Enabled)" indicator suffix for terminal output. 

## EXAMPLES
```bash
php spp.php ext:list
```
"""
}

out_dir = "docs/commands"

for filename, content in man_pages.items():
    path = os.path.join(out_dir, filename)
    with open(path, "w", encoding="utf-8") as f:
        f.write(content)

print(f"Generated {len(man_pages)} man pages in {out_dir}")
