# SPP CLI Manual

Detailed reference for all `spp.php` commands.

## Table of Contents
- [`admin:bootstrap`](#adminbootstrap)
- [`ai:providers`](#aiproviders)
- [`api:key:generate`](#apikeygenerate)
- [`api:key:revoke`](#apikeyrevoke)
- [`api:route:list`](#apiroutelist)
- [`app:config`](#appconfig)
- [`app:default`](#appdefault)
- [`app:list`](#applist)
- [`app:set-base`](#appsetbase)
- [`ask`](#ask)
- [`audit:lineage`](#auditlineage)
- [`blade:clear`](#bladeclear)
- [`blade:view`](#bladeview)
- [`bridge:call`](#bridgecall)
- [`cache:clear`](#cacheclear)
- [`cache:purge`](#cachepurge)
- [`cache:stats`](#cachestats)
- [`cache:warmup`](#cachewarmup)
- [`component:crud`](#componentcrud)
- [`config`](#config)
- [`config:export`](#configexport)
- [`config:import`](#configimport)
- [`config:sync`](#configsync)
- [`cron:flush`](#cronflush)
- [`cron:list`](#cronlist)
- [`cron:run`](#cronrun)
- [`db:verify`](#dbverify)
- [`dbsettings:export`](#dbsettingsexport)
- [`dbsettings:import`](#dbsettingsimport)
- [`delete:app`](#deleteapp)
- [`deploy:backups`](#deploybackups)
- [`deploy:build`](#deploybuild)
- [`deploy:cleanup`](#deploycleanup)
- [`deploy:cluster`](#deploycluster)
- [`deploy:env`](#deployenv)
- [`deploy:history`](#deployhistory)
- [`deploy:init`](#deployinit)
- [`deploy:logs`](#deploylogs)
- [`deploy:maintenance`](#deploymaintenance)
- [`deploy:plan`](#deployplan)
- [`deploy:pull`](#deploypull)
- [`deploy:push`](#deploypush)
- [`deploy:rollback`](#deployrollback)
- [`deploy:run`](#deployrun)
- [`di:list`](#dilist)
- [`diff:apply`](#diffapply)
- [`diff:compare`](#diffcompare)
- [`diff:history`](#diffhistory)
- [`diff:rollback`](#diffrollback)
- [`docs:build`](#docsbuild)
- [`drishyam:clear`](#drishyamclear)
- [`drishyam:compile`](#drishyamcompile)
- [`drishyam:theme:check`](#drishyamthemecheck)
- [`ent:edit`](#entedit)
- [`entity:crud`](#entitycrud)
- [`env:backup`](#envbackup)
- [`env:get`](#envget)
- [`env:set`](#envset)
- [`env:status`](#envstatus)
- [`env:token:rotate`](#envtokenrotate)
- [`event:dispatch`](#eventdispatch)
- [`event:fire`](#eventfire)
- [`event:list-listeners`](#eventlistlisteners)
- [`ext:disable`](#extdisable)
- [`ext:enable`](#extenable)
- [`ext:install`](#extinstall)
- [`ext:list`](#extlist)
- [`forge`](#forge)
- [`form:crud`](#formcrud)
- [`frontend:debug`](#frontenddebug)
- [`generate`](#generate)
- [`group:create`](#groupcreate)
- [`group:delete`](#groupdelete)
- [`group:edit`](#groupedit)
- [`group:list`](#grouplist)
- [`i18n:export`](#i18nexport)
- [`i18n:import`](#i18nimport)
- [`import:component`](#importcomponent)
- [`interdb:config`](#interdbconfig)
- [`interdb:mapping:add`](#interdbmappingadd)
- [`interdb:mapping:list`](#interdbmappinglist)
- [`interdb:mapping:remove`](#interdbmappingremove)
- [`lang:list`](#langlist)
- [`lang:scan`](#langscan)
- [`lang:set`](#langset)
- [`lekhak:generate-docs`](#lekhakgeneratedocs)
- [`lekhak:setup`](#lekhaksetup)
- [`list`](#list)
- [`live:status`](#livestatus)
- [`live:trigger`](#livetrigger)
- [`logger:clear`](#loggerclear)
- [`logger:tail`](#loggertail)
- [`make:app`](#makeapp)
- [`make:blade`](#makeblade)
- [`make:blade-project`](#makebladeproject)
- [`make:blade-scaffold`](#makebladescaffold)
- [`make:command`](#makecommand)
- [`make:controller`](#makecontroller)
- [`make:deployment`](#makedeployment)
- [`make:dotnet-service`](#makedotnetservice)
- [`make:drupal-bridge`](#makedrupalbridge)
- [`make:entity`](#makeentity)
- [`make:event`](#makeevent)
- [`make:eventhand`](#makeeventhand)
- [`make:form`](#makeform)
- [`make:go-service`](#makegoservice)
- [`make:java-service`](#makejavaservice)
- [`make:live-component`](#makelivecomponent)
- [`make:middleware`](#makemiddleware)
- [`make:migration`](#makemigration)
- [`make:mixed-paradigm`](#makemixedparadigm)
- [`make:model`](#makemodel)
- [`make:module`](#makemodule)
- [`make:node-service`](#makenodeservice)
- [`make:perl-service`](#makeperlservice)
- [`make:python-service`](#makepythonservice)
- [`make:react-component`](#makereactcomponent)
- [`make:scaffold`](#makescaffold)
- [`make:seeder`](#makeseeder)
- [`make:service`](#makeservice)
- [`make:sppview`](#makesppview)
- [`make:twig`](#maketwig)
- [`make:ux-component`](#makeuxcomponent)
- [`make:view`](#makeview)
- [`make:vue-component`](#makevuecomponent)
- [`man`](#man)
- [`man:generate`](#mangenerate)
- [`manifest:export`](#manifestexport)
- [`migrate`](#migrate)
- [`migrate:make`](#migratemake)
- [`module:disable`](#moduledisable)
- [`module:enable`](#moduleenable)
- [`module:install`](#moduleinstall)
- [`module:list`](#modulelist)
- [`module:setting:list`](#modulesettinglist)
- [`module:setting:update`](#modulesettingupdate)
- [`module:uninstall`](#moduleuninstall)
- [`module:update`](#moduleupdate)
- [`polyglot:async`](#polyglotasync)
- [`polyglot:list`](#polyglotlist)
- [`polyglot:run`](#polyglotrun)
- [`polyglot:status`](#polyglotstatus)
- [`polyglot:worker`](#polyglotworker)
- [`profile:report:generate`](#profilereportgenerate)
- [`profile:status`](#profilestatus)
- [`queue:list`](#queuelist)
- [`queue:work`](#queuework)
- [`schedule:run`](#schedulerun)
- [`serve`](#serve)
- [`service:crud`](#servicecrud)
- [`session:clean`](#sessionclean)
- [`session:destroy-all`](#sessiondestroyall)
- [`site:install`](#siteinstall)
- [`storage:clean`](#storageclean)
- [`storage:link`](#storagelink)
- [`storage:sync`](#storagesync)
- [`sys:debug`](#sysdebug)
- [`sys:seed`](#sysseed)
- [`sys:test:auto`](#systestauto)
- [`sys:upgrade`](#sysupgrade)
- [`test`](#test)
- [`test:blueprint`](#testblueprint)
- [`test:module`](#testmodule)
- [`test:monkey`](#testmonkey)
- [`test:run`](#testrun)
- [`theme:activate`](#themeactivate)
- [`tinker`](#tinker)
- [`ux:debug`](#uxdebug)
- [`verify:sovereignty`](#verifysovereignty)
- [`view:cache`](#viewcache)
- [`view:page:add`](#viewpageadd)
- [`view:page:list`](#viewpagelist)
- [`view:page:remove`](#viewpageremove)
- [`view:service:add`](#viewserviceadd)
- [`view:service:list`](#viewservicelist)
- [`view:service:remove`](#viewserviceremove)
- [`view:service:test`](#viewservicetest)
- [`xdb:describe`](#xdbdescribe)
- [`xdb:list-dbs`](#xdblistdbs)
- [`xdb:list-tables`](#xdblisttables)
- [`xdb:query`](#xdbquery)
- [`xdb:shell`](#xdbshell)

---

# NAME

`admin:bootstrap`

# SYNOPSIS

`php spp.php admin:bootstrap`

# PURPOSE

Initializes the SPP Administration environment by provisioning the default admin account directly into the lightweight XDB (XML Database).

# OPTIONS AVAILABLE

This command accepts no specific options.

# UNDER THE HOOD ACTIVITY

When executed, this command connects to the built-in XDB XML database by initializing an `SPPDB` instance with the connection string `xdb:dbname=default`. It sequentially inspects the database to ensure the foundational identity tables (`users`, `roles`, and `userroles`) exist, and if missing, executes the respective `CREATE TABLE` queries with string and auto-increment types. 

After ensuring schema compliance, it queries the `users` table for the existence of the `admin` account. If it does not exist, the script explicitly verifies the existence of the 'Admin' role, creating it and granting it an ID if absent. Finally, the command creates a secure hash of the default password (`admin123`) via PHP's `password_hash()` and directly inserts the new administrative user into the `users` table, then binds the user and role together via an `INSERT` into the `userroles` table. All activity is logged synchronously to standard output.

# EXAMPLES

Initialize the administration environment:
```bash
php spp.php admin:bootstrap
```

---

# NAME

`ai:providers`

# SYNOPSIS

`php spp.php ai:providers [--app=<appname>]`

# PURPOSE

Lists all AI providers that have been successfully registered within the current application's SPPAI module configuration.

# OPTIONS AVAILABLE

- `--app=<appname>` : Set the SPP Application context to evaluate. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

The command extracts the `--app` option from the arguments list to determine the target application context. Execution is encapsulated within `\SPP\Scheduler::withContext()` to guarantee the correct application environment variables and configurations are in place before fetching provider information.

Inside the callback, the `sppai` module is dynamically loaded and checked for availability. The script invokes the static method `\SPPMod\SPPAI\SPPAI::getRegistry()` to retrieve an associative array of all configured AI providers. If the registry is populated, the command iterates through the provider configurations, extracting the designated default `model` and the `active` boolean flag. It outputs the information in an aligned ASCII table using `str_pad()`, making it easy to identify which providers are properly configured and currently active within the application state.

# EXAMPLES

List registered providers for the default app:
```bash
php spp.php ai:providers
```

List providers for a specific application context:
```bash
php spp.php ai:providers --app=api
```

---

# NAME

`api:key:generate`

# SYNOPSIS

`php spp.php api:key:generate "<name>"`

# PURPOSE

Generates a new, highly secure, permanent API key token and records it in the database for client authentication.

# OPTIONS AVAILABLE

- `"<name>"` (Required Positional Argument) : A human-readable identifier or description for the generated key. Typically the name of the client or service.

# UNDER THE HOOD ACTIVITY

The command validates that the required `<name>` positional argument is present at `$args[2]`, throwing an error if absent. The API token itself is generated using a secure cryptographically random generator via `bin2hex(random_bytes(32))`, resulting in a robust 64-character hexadecimal string.

A standard `\SPPMod\SPPDB\SPPDB` database connection is instantiated. The command performs a basic schema verification by asserting the existence of the `api_keys` table. A unique identifier for the database row is created using `uniqid()`. A parameterized `INSERT` query is then executed on the `api_keys` table to persist the generated row ID, the descriptive token name, the raw API token, an active status flag (integer `1`), and the creation timestamp using `NOW()`. After successfully writing to the database, the raw API key token is echo'ed to the console for the user to securely capture.

# EXAMPLES

Generate an API key for the mobile application:
```bash
php spp.php api:key:generate "MobileApp_Production"
```

---

# NAME

`api:key:revoke`

# SYNOPSIS

`php spp.php api:key:revoke --token=<token>`

# PURPOSE

Revoke an existing API token to instantly prevent further client authentication using that key.

# OPTIONS AVAILABLE

- `--token=<token>` : (Required) The literal API token string that should be revoked.

# UNDER THE HOOD ACTIVITY

The command explicitly iterates through the CLI `$args` array to locate the `--token=` parameter and extracts the trailing substring value. If the parameter is missing, it outputs usage instructions to standard output and returns immediately. Upon successfully identifying the token, it prints a success message. 

*Note: In the current iteration of the framework, the actual database status revocation logic is stubbed out. It does not actively perform a SQL `UPDATE` or `DELETE` against the `api_keys` table.*

# EXAMPLES

Revoke a specific API token:
```bash
php spp.php api:key:revoke --token=a1b2c3d4e5f6g7h8...
```

---

# NAME

`api:route:list`

# SYNOPSIS

`php spp.php api:route:list`

# PURPOSE

Tabulate and display all exposed REST API routes configured by the SPPAPI module.

# OPTIONS AVAILABLE

This command accepts no specific options.

# UNDER THE HOOD ACTIVITY

When executed, the command first echoes an initialization message to standard output. It then programmatically checks if the `SPPAPI` framework module is loaded into the current environment by verifying the existence of the `\SPPMod\SPPAPI\SPPAPI` class using PHP's native `class_exists()` function. 

If the class is successfully located, indicating the API module is active, the command prints a static ASCII table illustrating the generic REST endpoint structures (e.g., `/api/v1/entities` accepting `GET` and `POST`, and `/api/v1/auth` accepting `POST`). If the class is missing, it notifies the user that the SPPAPI module is not active.

*Note: Currently, this command returns a statically defined list of routes as a demonstration stub, rather than dynamically parsing an application's internal routing tables.*

# EXAMPLES

List the exposed API endpoints:
```bash
php spp.php api:route:list
```

---

# NAME

`app:config`

# SYNOPSIS

`php spp.php app:config <app_name> [--base_url=...] [--table_prefix=...]`

# PURPOSE

Dynamically configures application-specific settings, such as the `base_url` or `table_prefix`, by directly modifying the global YAML settings definition.

# OPTIONS AVAILABLE

- `<app_name>` : (Required Positional Argument) The registered name of the target application to configure.
- `--base_url=...` : Set the fully qualified Base URL for the specified application.
- `--table_prefix=...` : Define a database table prefix specific to the target application context.

# UNDER THE HOOD ACTIVITY

The command immediately invokes its inherited `getArgument($args, 0)` method to isolate the `<app_name>`. Without it, execution halts with usage instructions. It defines the path to the framework's primary configuration file at `SPP_BASE_DIR . '/etc/global-settings.yml'`. If this file is absent from the filesystem, it aborts.

Using the `Symfony\Component\Yaml\Yaml::parseFile()` utility, it reads the entire YAML file into an associative PHP array. It verifies that the specified `<app_name>` exists under the `['apps']` configuration node. If validation passes, a reference to the specific app's array node is established. 

The command then extracts the `--base_url` and `--table_prefix` flags using `getOption()`. If either value is present, the target array node is mutated in memory, and an `$updated` boolean flag is toggled to true. When updates occur, the entire associative array is re-serialized back into YAML format via `Yaml::dump()` (utilizing an inline depth of 10 and indentation of 2 spaces) and written destructively back to `global-settings.yml` using `file_put_contents()`. If no flags are provided, the command harmlessly dumps the existing array node to the console via `print_r()`.

# EXAMPLES

Set the base URL and table prefix for the 'frontend' application:
```bash
php spp.php app:config frontend --base_url=https://www.example.com --table_prefix=fr_
```

View the current configuration for the 'api' application:
```bash
php spp.php app:config api
```

---

# NAME

`app:default`

# SYNOPSIS

`php spp.php app:default [--set=<app_name>]`

# PURPOSE

View or persistently set the default global CLI application context. Setting this determines which application environment is bootstrapped when executing commands that depend on application-level context without explicit flags.

# OPTIONS AVAILABLE

- `--set=<app_name>` : Update the settings to make the specified application the default context for future CLI executions.

# UNDER THE HOOD ACTIVITY

The command linearly scans the incoming CLI `$args` array to detect the `--set=` parameter, extracting the trailing substring value if present. It defines the canonical path to the CLI configuration at `SPP_APP_DIR . '/spp/etc/cli-settings.yml'`.

If the `--set` option is provided, the command attempts to load the existing CLI settings using `Symfony\Component\Yaml\Yaml::parseFile()`. If the file does not exist, it initializes an empty array. It then assigns the provided application name to the `['default_app']` array key. The modified array is immediately serialized back to YAML format via `Yaml::dump()` and written to the `cli-settings.yml` file using `file_put_contents()`. 

If the `--set` option is omitted, the command performs a read-only operation: parsing the YAML file (or defaulting to an empty array) and echoing the value of the `default_app` key, falling back to the string `'default'` if the key remains unconfigured.

# EXAMPLES

Check the current default CLI application context:
```bash
php spp.php app:default
```

Set the default CLI context to 'admin_panel':
```bash
php spp.php app:default --set=admin_panel
```

---

# NAME

`app:list`

# SYNOPSIS

`php spp.php app:list`

# PURPOSE

Scans the system configuration and directories to display a comprehensive list of all registered and discovered SPP applications.

# OPTIONS AVAILABLE

This command accepts no specific options.

# UNDER THE HOOD ACTIVITY

The command attempts to resolve and parse the framework configuration file at `SPP_BASE_DIR . '/etc/global-settings.yml'` via `Symfony\Component\Yaml\Yaml::parseFile()`. It extracts the `apps` dictionary, which serves as the formal registry. 

To ensure complete visibility (even for unregistered apps), the command then scans the filesystem directory `SPP_APP_DIR . '/spp/etc/apps'` using `scandir()`. It iterates over the results, and if a valid directory is found that does not exist in the YAML registry keys, it appends it to the internal list of applications. 

With the complete array of application names assembled, it iterates over them to cross-reference metadata. It extracts properties like `type` (defaulting to 'native'), `base_url` (defaulting to `/appname`), and `table_prefix`. Furthermore, it evaluates the global `base_app` configuration key, appending a `[BASE]` tag to the application name that matches it. The final output is formatted into a fixed-width ASCII table using PHP's `str_pad()` function and echoed to standard output.

# EXAMPLES

List all applications:
```bash
php spp.php app:list
```

---

# NAME

`app:set-base`

# SYNOPSIS

`php spp.php app:set-base <app_name>`

# PURPOSE

Modifies the global configuration to designate a specific application as the primary or "base" application for routing and context purposes.

# OPTIONS AVAILABLE

- `<app_name>` : (Required Positional Argument) The registered name of the application to promote to the base context.

# UNDER THE HOOD ACTIVITY

Execution begins by asserting the presence of the `<app_name>` positional argument at index 2 of the CLI argument array. If missing, it outputs the expected usage. It defines the path to the configuration matrix at `SPP_BASE_DIR . '/etc/global-settings.yml'` and aborts if the file cannot be located.

The command parses the YAML payload into memory utilizing `Symfony\Component\Yaml\Yaml::parseFile()`. It conducts a validation check to ensure the provided `<app_name>` actually exists within the `['apps']` configuration key. If the application is unregistered, it throws an error and exits.

Upon successful validation, the top-level configuration key `['base_app']` is overridden with the newly designated application name. The modified array structure is serialized via `Yaml::dump()` (configured with a depth parameter of 10 and 2 spaces indentation) and persisted directly to the `global-settings.yml` file using `file_put_contents()`, making the state change immediate across the framework.

# EXAMPLES

Set the 'frontend' application as the global base:
```bash
php spp.php app:set-base frontend
```

---

# NAME

`ask`

# SYNOPSIS

`php spp.php ask "<question>"`

# PURPOSE

Interacts with the SPP AI Mentor to provide intelligent onboarding answers. If the AI Daemon is unreachable, it gracefully degrades to a localized keyword search across the documentation repository.

# OPTIONS AVAILABLE

- `"<question>"` : (Required Positional Argument) A natural language question. All trailing arguments are concatenated into a single query string.

# UNDER THE HOOD ACTIVITY

The command concatenates all trailing arguments to form the complete question string. It then attempts an inter-process communication (IPC) call to the AI Daemon via the `\SPP\PolyglotBridge::call()` method. The target bridge call explicitly requests the `python` language driver to execute the `handle_spp_request` function within the `services/python/ai_mentor.py` module, operating in daemon mode (`true`). It passes an associative array payload dictating the `ask` action and the raw question. 

If the bridge returns a valid payload without an `error` key, the command echoes the AI Mentor's answer to the console.

If an exception occurs (e.g., the daemon is offline, or the bridge fails), the `catch` block is triggered, activating the "Graceful Degradation" fallback. The `fallbackSearch()` method aggressively strips punctuation from the question and excludes basic English stop words to produce an array of search keywords. It then instantiates a `RecursiveDirectoryIterator` to traverse `SPP_APP_DIR . '/documentation'`. For every markdown file (`.md`) found, the system loads the file contents into memory, converts it to lowercase, and performs repeated `str_contains()` checks against the extracted keywords, incrementing a score variable. It collates the files with positive scores, sorts them dynamically using `usort()` in descending order, and displays the paths to the top three matched files to the user as alternative reading material.

# EXAMPLES

Ask a specific technical question:
```bash
php spp.php ask "How do I implement custom middleware?"
```

---

# NAME

`audit:lineage`

# SYNOPSIS

`php spp.php audit:lineage [--app=<appname>]`

# PURPOSE

Traverses and verifies the cryptographic Merkle-DAG trace logs, ensuring the immutability and integrity of tracked system state transactions.

# OPTIONS AVAILABLE

- `--app=<appname>` : Target a specific application's lineage log rather than the global default log.

# UNDER THE HOOD ACTIVITY

By default, the command establishes its verification target against the global state log situated at `SPP_APP_DIR . '/var/logs/merkle_lineage.log'`. It scans the CLI arguments for the presence of the `--app=` parameter. If detected, it mutates the target path to isolate the specific application's log directory: `SPP_APP_DIR . '/src/' . $appName . '/var/logs/merkle_lineage.log'`.

The command uses `file_exists()` to ensure the log target is physically present on the disk. If no log exists, it alerts the user that no immutable state transactions have been recorded. 

If the log exists, the command utilizes the native PHP `file()` function, passing `FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES` to efficiently load the entire log sequence into an array while bypassing structural whitespace. It counts the total number of lines (which ostensibly map to continuous cryptographic DAG state signatures) and echoes the total count back to the console, affirming that the "mathematical Merkle root hash sequence is uncompromised." 

*Note: The current implementation performs an optimistic line-count verification rather than executing a full mathematical recalculation of the Merkle root hashes.*

# EXAMPLES

Audit the global state trace:
```bash
php spp.php audit:lineage
```

Audit a specific application's state trace:
```bash
php spp.php audit:lineage --app=financial_ledger
```

---

## `blade:clear`

**Purpose**: Clear the compiled Blade view cache

### Synopsis
```bash
php spp.php blade:clear [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `blade:view`

**Purpose**: Manage Blade views (list, create, delete)

### Synopsis
```bash
php spp.php blade:view [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `bridge:call`

**Purpose**: Internal RPC bridge to invoke PHP methods from Polyglot clients

### Synopsis
```bash
php spp.php bridge:call [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \ReflectionMethod.


---

# NAME
**cache:clear** - Clear the entire SPP Cache directory

# SYNOPSIS
`php spp.php cache:clear`

# PURPOSE
Flushes all cached data stored by the application. This is typically used after deploying new code, altering configurations, or when system memory needs to be purged to eliminate stale views and records.

# OPTIONS AVAILABLE
This command takes no arguments or options.

# UNDER THE HOOD ACTIVITY
The command retrieves the singleton instance of the caching engine by calling `\SPP\Cache::getInstance()`. It then invokes the `flush()` method on this instance. The `flush()` method is responsible for invalidating and purging all cache pools, which natively targets the filesystem cache directory or external data stores (like Redis or Memcached), depending on the active cache configuration. Finally, it outputs a success message upon completion.

# EXAMPLES
Clear the application cache:
`php spp.php cache:clear`

---

## `cache:purge`

**Purpose**: Purge cache tags or URLs from the reverse proxy (Varnish/CDN).

### Synopsis
```bash
php spp.php cache:purge [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.
- Makes outbound HTTP requests to external APIs or services.


---

## `cache:stats`

**Purpose**: Display cache driver statistics

### Synopsis
```bash
php spp.php cache:stats [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from CacheStatsCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Interacts with the application cache layer (Redis/Memcached).


---

## `cache:warmup`

**Purpose**: Warm up common application caches

### Synopsis
```bash
php spp.php cache:warmup [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from CacheWarmupCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Interacts with the application cache layer (Redis/Memcached).


---

## `component:crud`

**Purpose**: Manage SPP UI components (list, create, edit, delete)

### Synopsis
```bash
php spp.php component:crud [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `config`

**Purpose**: Manage framework and application configuration

### Synopsis
```bash
php spp.php config [OPTIONS]
```

### Extended Usage
```text
Usage: spp config [get|set|delete|list|cache|clear] [key] [value]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `config:export`

**Purpose**: Export database tables and global settings to SQL, SQLite, or XDB format

### Synopsis
```bash
php spp.php config:export [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, \PDO, \DOMDocument.


---

## `config:import`

**Purpose**: Import database tables and settings from an exported SQL, SQLite, or XDB file

### Synopsis
```bash
php spp.php config:import [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, \PDO, \DOMDocument.


---

## `config:sync`

**Purpose**: Synchronize framework configurations (e.g. workflows, dynamic fields) to DB schemas or system registries

### Synopsis
```bash
php spp.php config:sync [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `cron:flush`

**Purpose**: Clear cron history and lock files

### Synopsis
```bash
php spp.php cron:flush [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from CronFlushCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Bootstraps a full application execution context via Scheduler.


---

## `cron:list`

**Purpose**: List all registered scheduled tasks

### Synopsis
```bash
php spp.php cron:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from CronListCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \ReflectionClass.


---

## `cron:run`

**Purpose**: Execute pending cron jobs manually

### Synopsis
```bash
php spp.php cron:run [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from CronRunCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

# db:verify

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

---

# dbsettings:export

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

---

# dbsettings:import

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

---

# delete:app

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

---

# NAME
**deploy:backups** - List available snapshot backups on a remote target for rollback

# SYNOPSIS
`php spp.php deploy:backups <target_uri> [--key=YOUR_API_KEY]`

# PURPOSE
Queries a remote deployment node to list all available rollback snapshots, allowing administrators to review backup histories before initiating a restoration.

# OPTIONS AVAILABLE
- `<target_uri>` : **Required.** The HTTP URI of the target server to query.
- `--key=<api_key>` : **Optional.** The API authentication key to authorize the request on the remote node. Defaults to `default_cli_key`.

# UNDER THE HOOD ACTIVITY
The command extracts the target URI and the optional `--key` flag from the runtime arguments. It calls `\SPPMod\Sppdeploy\Deployer\TargetConnection::resolve($target, $apiKey)` to instantiate a client for the remote node. Using this connection, it invokes the `getBackups()` method, which performs an HTTP request to the target server. The target responds with a JSON array of backup metadata. The command checks the `status` flag of the response. If successful, it parses the `backups` array and formats a tabular view containing the backup date, filename (snapshot ID), and physical file size rounded to MB.

# EXAMPLES
Check backups on the staging server:
`php spp.php deploy:backups https://staging.example.com --key=secret_123`

---

# NAME
**deploy:build** - Create a local deployment artifact bundle without pushing

# SYNOPSIS
`php spp.php deploy:build <target_uri> [--key=YOUR_API_KEY] [--no-db] [--no-files]`

# PURPOSE
Calculates the delta (diff) between the local environment and a remote target server, then builds a compressed ZIP artifact containing only the updated files and necessary database schema modifications. The artifact is saved locally and can be deployed at a later time.

# OPTIONS AVAILABLE
- `<target_uri>` : **Required.** The URI of the target environment to compare against.
- `--key=<api_key>` : **Optional.** API key to authenticate with the remote node.
- `--no-db` : **Optional.** Skips the database schema comparison.
- `--no-files` : **Optional.** Skips the file system structure comparison.

# UNDER THE HOOD ACTIVITY
The command initializes a `TargetConnection` to communicate with the remote server. If `--no-files` is absent, it uses `\SPPMod\SPPDeploy\Scanner\ProjectScanner` to hash all local files within `SPP_BASE_DIR`. If `--no-db` is absent, `\SPPMod\SPPDeploy\Scanner\DbScanner` runs to capture the local schema state. These hashes are sent to the target via the `getDiff()` API call. The remote server calculates the difference and returns arrays of files and tables to create, update, or delete. If changes exist, the command provisions a local artifact directory (`var/builds`) and initiates a `\ZipArchive`. It iterates over the 'create' and 'update' file arrays and adds them to the ZIP. For database changes, it checks the local PDO driver (MySQL or SQLite), runs the native `SHOW CREATE TABLE` (or equivalent), and bundles the statements into a `db_snapshot.sql` file within the ZIP. Finally, it creates a JSON manifest of the diff operations alongside the ZIP artifact.

# EXAMPLES
Build an artifact for the production environment:
`php spp.php deploy:build https://api.example.com --key=secret`

Build an artifact excluding database changes:
`php spp.php deploy:build https://api.example.com --no-db`

---

# NAME
**deploy:cleanup** - Prune old rollback snapshots from the remote target server

# SYNOPSIS
`php spp.php deploy:cleanup <target_uri> [--keep=5] [--key=YOUR_API_KEY]`

# PURPOSE
Requests the remote server to delete old deployment backup snapshots to free up disk space, retaining only a specified number of recent backups.

# OPTIONS AVAILABLE
- `<target_uri>` : **Required.** The URI of the target environment.
- `--keep=<integer>` : **Optional.** The number of recent backup snapshots to retain. Defaults to 5.
- `--key=<api_key>` : **Optional.** API key for remote authentication.

# UNDER THE HOOD ACTIVITY
The command extracts the target URI, the optional API key, and parses the `--keep` argument into an integer. It establishes a remote client instance using `\SPPMod\Sppdeploy\Deployer\TargetConnection::resolve()`. It then invokes the `cleanupBackups($keep)` method, passing the retention integer. This method transmits an HTTP request instructing the remote environment to sort its backup directory and permanently unlink (delete) any archives older than the defined retention threshold. The remote node returns a JSON status payload, which the CLI interprets and displays as a success or failure notification.

# EXAMPLES
Keep only the latest 3 backups on staging:
`php spp.php deploy:cleanup https://staging.example.com --keep=3`

---

# NAME
**deploy:cluster** - Deploy to a multi-server cluster sequentially

# SYNOPSIS
`php spp.php deploy:cluster <cluster_name> [--force] [-y] [other_push_flags]`

# PURPOSE
Automates the deployment process across an entire cluster of multiple remote nodes defined in a central configuration file. This guarantees that all instances in a load-balanced pool receive the updated application artifact.

# OPTIONS AVAILABLE
- `<cluster_name>` : **Required.** The alias of the cluster group defined in `.sppdeploy.yml`.
- `--force` or `-y` : **Optional.** Bypasses the interactive manual confirmation prompt.
- `[other_push_flags]` : **Optional.** Any additional flags (like `--no-db`) are transparently passed down to the underlying `deploy:push` command for each node.

# UNDER THE HOOD ACTIVITY
The command reads and parses the YAML configuration file located at `SPP_BASE_DIR/.sppdeploy.yml`. It validates that the `<cluster_name>` exists and maps to an array of remote URIs (nodes). Unless `--force` or `-y` is present, it stalls execution and requires the user to input 'Y' on standard input (`php://stdin`) before proceeding. Once confirmed, it instantiates the `DeployPushCommand` logic in memory. It iterates through the array of node URIs, formatting an arguments array (passing `--force` automatically alongside any custom user flags), and executes the push command serially for each node. If the deployment throws an Exception on any single node, the loop breaks instantly, halting the remainder of the cluster rollout to prevent inconsistent state distribution. Finally, it prints a summary of successfully updated nodes vs the total expected pool.

# EXAMPLES
Deploy to the production cluster, skipping prompts:
`php spp.php deploy:cluster production --force`

Deploy to the web-workers cluster without database updates:
`php spp.php deploy:cluster web-workers --no-db`

---

# NAME

deploy:env - Manage remote environment variables securely

# SYNOPSIS

`php spp.php deploy:env <target_uri> push --key=MY_KEY --value=MY_VALUE [--key_api=YOUR_API_KEY]`

# PURPOSE

The `deploy:env` command is used to securely push and update environment variable key-value pairs to a remote deployment target. It allows developers to configure environment variables (such as database credentials, API tokens, or feature flags) without needing to SSH directly into the remote server or manually edit `.env` files.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The connection URI or identifier for the remote target environment where the environment variable should be set.
*   `push` (Required)
    The action to perform. Currently, only the `push` action is supported.
*   `--key=MY_KEY` (Required)
    The name of the environment variable key you wish to set or update.
*   `--value=MY_VALUE` (Required)
    The value to assign to the specified environment variable key.
*   `--key_api=YOUR_API_KEY` (Optional)
    The API token/key used to authenticate with the remote target. If not provided, it defaults to `default_cli_key`.

# UNDER THE HOOD ACTIVITY

When `deploy:env` is executed, the command first validates the presence of the required arguments (`target_uri` and the `push` action). It then iterates over the provided arguments to extract the values for `--key`, `--value`, and optionally `--key_api`. If the required key or value is missing, the command immediately halts execution and displays an error message.

Once the parameters are parsed, the command initializes a connection to the remote server using the `SPPMod\Sppdeploy\Deployer\TargetConnection::resolve()` method, passing the target URI and the API key. This abstract connection layer then sends a remote procedure call or HTTP API request via the `$conn->pushEnvKey($envKey, $envValue)` method. The remote server, assuming it runs the SPP deployment receiver, processes this payload and typically modifies its active `.env` file or environment configuration safely, returning a structured JSON response indicating success or failure. The CLI command parses this response and outputs a localized success or failure message to the standard output.

# EXAMPLES

**Push an API endpoint configuration to a staging server:**
```bash
php spp.php deploy:env http://staging.example.com push --key=PAYMENT_GATEWAY_URL --value=https://api.sandbox.paypal.com --key_api=secret_token_123
```

**Set a debugging flag on the production server:**
```bash
php spp.php deploy:env http://prod.example.com push --key=APP_DEBUG --value=false --key_api=my_prod_secret
```

---

# NAME

deploy:history - Fetch and display deployment history from a remote target

# SYNOPSIS

`php spp.php deploy:history <target_uri> [--key=YOUR_API_KEY]`

# PURPOSE

The `deploy:history` command retrieves the historical log of all deployment events executed against a specific remote target environment. It displays a tabular output of past deployments, including timestamps, originator IPs, deployment statuses, file/database operation counts, and associated commit or status messages.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The connection URI or identifier of the remote environment from which to fetch the deployment history.
*   `--key=YOUR_API_KEY` (Optional)
    The secure API token used to authenticate the request with the remote server. Defaults to `default_cli_key` if omitted.

# UNDER THE HOOD ACTIVITY

Upon execution, the command verifies that a target URI has been supplied. It then parses the arguments for an optional `--key` flag to override the default API key. Next, it establishes a communication channel with the remote target using the `SPPMod\Sppdeploy\Deployer\TargetConnection::resolve()` factory method.

The command triggers the `$conn->getHistory()` method, which performs a secure request to the remote server's deployment API to fetch its internal logs or database records of past deployments. The remote server responds with a structured payload containing a status indicator and a `history` array. 

If the request is successful and history exists, the command prepares a formatted ASCII table in standard output. It iterates over the retrieved history entries, formatting timestamps, resolving IP addresses, and extracting deployment metadata like `filesCount` (number of files modified) and `dbCount` (number of database migrations applied). To maintain console readability, it normalizes multi-line deployment messages into single lines and truncates any message longer than 30 characters using an ellipsis. Finally, it pads the columns to fixed widths and echoes the tabular data.

# EXAMPLES

**View deployment history for the production environment using a custom API key:**
```bash
php spp.php deploy:history http://prod.example.com --key=my_secure_token
```

**View history for a local testing node:**
```bash
php spp.php deploy:history http://localhost:8080
```

---

# NAME

deploy:init - Initialize SPPDeploy configuration for a project

# SYNOPSIS

`php spp.php deploy:init`

# PURPOSE

The `deploy:init` command scaffolds the foundational configuration files required by the SPP Framework's deployment system. It creates a deployment ignore file (`.sppignore`) to prevent sensitive or unnecessary files from being uploaded, and an interactive YAML configuration file (`.sppdeploy.yml`) that defines deployment environments and authentication tokens.

# OPTIONS AVAILABLE

This command takes no arguments. However, it relies on interactive standard input (`STDIN`) to prompt the user for the name of the primary deployment environment.

# UNDER THE HOOD ACTIVITY

When executed, the command determines the project root by resolving the directory containing the SPP base directory (`dirname(SPP_BASE_DIR)`).

First, it checks for the existence of an `.sppignore` file in the root directory. If one is not found, it generates a default `.sppignore` containing common exclusion patterns, such as `/.git`, framework cache directories (`/spp/var/cache`), session data, log files, backups, the deployment configuration itself, and the `.maintenance` flag file. This ensures that a subsequent deployment does not accidentally sync massive directories or sensitive local configurations to the remote server.

Next, it checks for an existing `.sppdeploy.yml` configuration file. If missing, the command opens a stream to `php://stdin` and interactively prompts the user to enter a name for their primary environment (defaulting to `production` if left blank). The system then generates a cryptographically secure 64-character hexadecimal deployment token by calling `bin2hex(random_bytes(32))`. 

A scaffolded `.sppdeploy.yml` file is then written to disk, containing the specified environment name, a placeholder URL, and the generated token. The YAML file also includes commented-out examples of advanced deployment features such as webhook notifications, post-deployment commands, and data anonymization rules. The console finally outputs the generated token, instructing the developer to securely store it as the `SPP_DEPLOY_TOKEN` environment variable on the remote server to enable authenticated deployments.

# EXAMPLES

**Initialize the deployment configuration interactively:**
```bash
php spp.php deploy:init
```
*(The command will prompt you for the primary environment name and subsequently generate the required `.sppignore` and `.sppdeploy.yml` files).*

---

# NAME

deploy:logs - View and tail remote application error logs securely over HTTP

# SYNOPSIS

`php spp.php deploy:logs <target_uri> [--key=YOUR_API_KEY] [--tail] [--lines=100]`

# PURPOSE

The `deploy:logs` command allows developers to remotely inspect the application log files of a target environment without requiring direct file system or SSH access. It supports fetching a specific number of recent log lines and optionally "tailing" the log in real-time by polling the remote server for new entries.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The connection URI or identifier of the remote environment.
*   `--key=YOUR_API_KEY` (Optional)
    The authentication token required to access the remote server's logs. Defaults to `default_cli_key`.
*   `--tail` (Optional)
    If specified, the command will continuously poll the remote server for new log entries, similar to the Unix `tail -f` command.
*   `--lines=100` (Optional)
    The number of historical log lines to fetch during the initial request. Defaults to 100.

# UNDER THE HOOD ACTIVITY

The command parses the CLI arguments, extracting the target URI, API key, the `--tail` flag, and the number of lines to request. It then establishes a connection handler via `TargetConnection::resolve()`.

It performs an initial HTTP request to the remote server by invoking `$conn->getLogs(-1, $lines)`. The remote system is expected to read the application log file from the end, returning the last `$lines` number of lines. The response includes the contents of the log file, the absolute file path on the remote system, and an `offset` integer indicating the current byte position at the end of the file.

The command outputs the file path and the retrieved log lines to the console. If the `--tail` flag is not provided, execution terminates here.

If `--tail` is active, the command enters an infinite `while (true)` loop. In each iteration, it pauses execution for 2 seconds (`sleep(2)`) to avoid overwhelming the network and server. It then calls `$conn->getLogs($offset, 0)`, sending the previously recorded byte offset back to the server. The remote server seeks to that specific byte offset in the log file, reads any new data appended since the last request, and returns the new chunk along with an updated offset. The command then echoes the new lines to the console and updates its local offset state, creating a seamless real-time stream until interrupted by the user (`Ctrl+C`).

# EXAMPLES

**Fetch the last 50 lines of logs from a remote server:**
```bash
php spp.php deploy:logs http://prod.example.com --key=secret_key --lines=50
```

**Tail the remote application logs continuously:**
```bash
php spp.php deploy:logs http://staging.example.com --tail
```

---

# NAME

deploy:maintenance - Toggle manual maintenance mode on a target environment

# SYNOPSIS

`php spp.php deploy:maintenance <target_uri> --on|--off [--key=YOUR_API_KEY]`

# PURPOSE

The `deploy:maintenance` command is used to enable or disable maintenance mode on an application instance. When enabled, the application typically blocks user access and displays a maintenance page. This command is versatile as it can act upon a remote deployment target or the local environment directly.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The connection URI of the remote server, or the special keyword `local` to toggle maintenance mode on the current local instance.
*   `--on` / `--off` (Required)
    Specifies the desired state of maintenance mode. You must provide exactly one of these flags.
*   `--key=YOUR_API_KEY` (Optional)
    The API token to authenticate the request if toggling maintenance mode on a remote server.

# UNDER THE HOOD ACTIVITY

The command's behavior branches significantly depending on whether the target is `local` or a remote URL.

If the target is set to `local`, the command interacts directly with the local file system. It resolves the project root directory and targets a hidden `.maintenance` file within it. If `--on` is passed, it uses `file_put_contents` to create the `.maintenance` file, writing a default message: `"Site is undergoing manual maintenance. Please check back later."` The framework's core lifecycle hooks likely check for the existence of this file early in the request pipeline to halt execution and serve a generic 503 response. If `--off` is passed, the command uses `unlink()` to delete the `.maintenance` file if it exists, restoring normal application functionality.

If the target is a remote URI, the command delegates the operation to the deployment connection layer (`TargetConnection::resolve()`). It triggers `$conn->setMaintenanceMode($state)`, passing `"on"` or `"off"`. This fires an API request to the remote deployment receiver, which then performs the exact file system operations (creating or deleting its own `.maintenance` file) securely over the network. The remote target responds with a JSON payload indicating success or failure, which the CLI evaluates to print the final status message.

# EXAMPLES

**Enable maintenance mode on the local machine:**
```bash
php spp.php deploy:maintenance local --on
```

**Disable maintenance mode on a remote production server:**
```bash
php spp.php deploy:maintenance http://prod.example.com --off --key=secure_admin_key
```

---

# NAME

deploy:plan - Perform a dry run to view file and database changes before deploying

# SYNOPSIS

`php spp.php deploy:plan <target_uri> [--key=YOUR_API_KEY] [--no-db]`

# PURPOSE

The `deploy:plan` command analyzes the local workspace and database against a remote target server to determine what will be changed during a push deployment. It serves as a "dry run" or pre-flight check, computing the exact diffs for files and database schemas without making any actual modifications to the remote environment.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The connection URI of the remote environment to compare against.
*   `--key=YOUR_API_KEY` (Optional)
    The secure API token to authenticate the request with the remote server.
*   `--no-db` (Optional)
    Skips the database schema comparison. If set, only file differences will be calculated and displayed.

# UNDER THE HOOD ACTIVITY

When execution begins, the command instantiates a `FileScanner` to traverse the local project directory (defined by `SPP_BASE_DIR`), generating cryptographic hashes for all tracked files while respecting `.sppignore` rules. If the `--no-db` flag is omitted, it also instantiates a `DbScanner` to inspect the local database schema, generating representations of the current database tables.

The CLI then sends these local state hashes to the remote server via `$conn->getDiff()`. The remote deployment receiver compares the incoming hashes against its own current state, categorizing files and database tables into three arrays: `create`, `update`, and `delete`. The receiver responds with this aggregated `diff` payload.

The command parses this payload and aggregates the total counts. If no changes are detected, it exits cleanly. Otherwise, it prints a structured "PRE-FLIGHT PLAN". It lists the number of files to be created, updated, and explicitly names any files scheduled for deletion.

For database changes, the command dynamically generates the exact raw SQL statements that would be executed on the remote server. It resolves the local PDO driver (`sqlite` or `mysql`) and queries the database engine (e.g., `SHOW CREATE TABLE`) for any tables marked as `create` or `update`. It structures `DROP TABLE IF EXISTS` and `CREATE TABLE` statements, displaying them in the terminal as "PROPOSED SQL STATEMENTS". This provides the developer with full transparency regarding destructive database operations before committing to a push.

# EXAMPLES

**Preview deployment changes to the staging server:**
```bash
php spp.php deploy:plan http://staging.example.com --key=my_secure_token
```

**Preview only file changes, ignoring the database:**
```bash
php spp.php deploy:plan http://prod.example.com --no-db
```

---

# NAME

deploy:pull - Pull the entire state (files and database) from a remote server to the local workspace

# SYNOPSIS

`php spp.php deploy:pull <target_uri> [--key=YOUR_API_KEY] [-y|--force]`

# PURPOSE

The `deploy:pull` command is the inverse of `deploy:push`. It downloads a complete snapshot of a remote application's file system and database, automatically extracting and restoring them onto the local development machine. This command is highly destructive to the local environment and is primarily used for synchronizing a local environment with production data.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The connection URI of the remote environment from which to pull data.
*   `--key=YOUR_API_KEY` (Optional)
    The secure API token for remote authentication. If not provided, it falls back to the `SPP_DEPLOY_TOKEN` environment variable or `default_cli_key`.
*   `-y` or `--force` (Optional)
    Bypasses the interactive confirmation prompt, automatically proceeding with the destructive pull.

# UNDER THE HOOD ACTIVITY

Because pulling overwrites local data, the command first issues a severe warning to standard output and halts execution, requiring the user to type 'Y' or 'y' into standard input (`STDIN`) to proceed. This safeguard is disabled if the `--force` flag is provided.

Upon confirmation, the CLI triggers the `$conn->getExport()` method. The remote server executes a full export procedure: dumping its database to a `.sql` file, zipping its application directories, and returning the base64-encoded binary archive in a JSON response. 

The command extracts the base64 payload (`$resp['archive']`), decodes it, and writes it to a temporary zip archive (`var/cache/deploy_pull.zip`). It then instantiates PHP's native `ZipArchive` class to extract the contents directly over the local project root (`dirname(SPP_BASE_DIR)`), forcefully overwriting existing local files.

After file extraction, the command checks for a specific artifact named `db_snapshot.sql` at the root directory. If found, it establishes a PDO connection to the local database using the `SPPMod\SPPDB\SPPDB` wrapper. To prevent foreign key constraint errors during a massive import, it dynamically detects if the driver is MySQL and disables foreign key checks (`SET FOREIGN_KEY_CHECKS=0;`). It then executes the entire SQL dump via `$pdo->exec($sql)`, effectively dropping and recreating the local database state to mirror the remote server. Once completed, foreign key checks are re-enabled, and the `db_snapshot.sql` and temporary zip files are deleted to clean up the workspace.

# EXAMPLES

**Pull production data to your local machine (interactive):**
```bash
php spp.php deploy:pull http://prod.example.com --key=admin_token
```

**Force a pull from staging without confirmation:**
```bash
php spp.php deploy:pull http://staging.example.com -y
```

---

# NAME

deploy:push - Push the local project state to a remote SPP target server

# SYNOPSIS

`php spp.php deploy:push <target_uri> [--key=YOUR_API_KEY] [--dry-run] [--no-db] [--no-files] [-y|--force] [--artifact=PATH]`

# PURPOSE

The `deploy:push` command is the core deployment engine of the SPP Framework. It synchronizes your local application code and database schema with a remote server. It features an intelligent delta-diffing system, secure chunked uploads, remote health checks, and pre/post-deployment hook execution.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The destination remote environment URI.
*   `--key=YOUR_API_KEY` (Optional)
    Authentication token. Defaults to the `SPP_DEPLOY_TOKEN` environment variable.
*   `--dry-run` (Optional)
    Performs all diffing and checks but skips actual transmission and deployment.
*   `--no-db` (Optional)
    Skips scanning and pushing database schema changes.
*   `--no-files` (Optional)
    Skips scanning and pushing file system changes.
*   `-y` / `--force` (Optional)
    Skips the interactive confirmation prompt before pushing.
*   `--artifact=PATH` (Optional)
    Instead of dynamically building a payload, push a pre-compiled zip artifact file (and its corresponding `.json` manifest).

# UNDER THE HOOD ACTIVITY

The `deploy:push` pipeline is extensive and follows a strict lifecycle:

1.  **Pre-Deploy Hooks**: It parses the local `.sppdeploy.yml` configuration file. If `pre_deploy` scripts are defined, it executes them locally via `exec()`. If any script fails (returns a non-zero exit code), the deployment immediately aborts.
2.  **Artifact Mode**: If `--artifact` is provided, the command bypasses scanning. It loads the compiled `.zip` and `.json` manifest directly from disk and jumps to the transmission phase.
3.  **State Scanning**: If not using an artifact, `ProjectScanner` and `DbScanner` are initialized to hash local files and database tables.
4.  **Health & Environment Checks**: It pings the remote server (`$conn->getHealth()`) to ensure the remote `zip` extension is loaded and the `spp/var` directory is writable. It also fetches the remote `.env` keys, comparing them against the local environment variables. Any keys present locally but missing remotely will trigger a console warning.
5.  **Delta Diffing**: Local hashes are sent to the remote server to compute the delta diff (`create`, `update`, `delete` arrays for both files and db). If no changes exist, it exits.
6.  **Confirmation**: Unless `--force` or `--dry-run` is active, it presents a summary of operations and pauses for user confirmation via `STDIN`.
7.  **Payload Generation**: It creates a temporary ZIP archive (`var/cache/deploy_payload.zip`). It bundles only the files marked for `create` or `update`. If database changes are required, it generates the specific `DROP` and `CREATE` SQL statements for the affected tables and bundles them as `db_snapshot.sql` inside the ZIP.
8.  **Chunked Transmission**: To handle large deployments over restricted networks (e.g., Cloudflare limits), the CLI splits the zip file into 2MB chunks. It iterates through the chunks, encoding them in base64, and uploading them sequentially via `$conn->uploadChunk()`. A unique `sessionId` tracks the upload state on the server.
9.  **Finalization**: On the final chunk upload, the server reconstructs the ZIP, applies the file changes, executes the SQL snapshot, fires any defined remote webhooks, and returns a final success response containing webhook statuses.

# EXAMPLES

**Standard push to production:**
```bash
php spp.php deploy:push http://prod.example.com --key=my_secure_token
```

**Push a pre-built artifact archive forcefully:**
```bash
php spp.php deploy:push http://staging.example.com --artifact=builds/release-v1.zip -y
```

---

# NAME

deploy:rollback - Roll back a remote target to a specific snapshot backup ID

# SYNOPSIS

`php spp.php deploy:rollback <target_uri> <backup_id> [--key=YOUR_API_KEY] [--force]`

# PURPOSE

The `deploy:rollback` command instantly reverts a remote deployment environment to a previously saved state. During a successful push deployment, the remote server typically takes an automated backup of its file system and database before applying changes. This command allows you to restore one of those specific backups using its unique identifier.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The target remote environment URI.
*   `<backup_id>` (Required)
    The specific identifier of the remote backup to restore (usually an ID or timestamp string generated by the server).
*   `--key=YOUR_API_KEY` (Optional)
    The API token to authenticate the request.
*   `--force` or `-y` (Optional)
    Bypasses the interactive confirmation prompt, automatically executing the rollback.

# UNDER THE HOOD ACTIVITY

Due to the destructive nature of replacing the active codebase and production database, the command first presents a stark warning to standard output. It requires the developer to explicitly type 'Y' or 'y' into standard input to confirm, unless the `--force` flag was passed at execution.

Once confirmed, the CLI establishes an HTTP API connection to the target server using the deployer's connection layer. It triggers `$conn->executeRollback($backupId)`. 

The CLI acts purely as an orchestrator for this command. The actual heavy lifting occurs on the remote server. The remote deployment receiver locates the backup archive corresponding to the provided ID, unpacks it, forcefully replaces the active application files, drops current database tables, and executes the backup's `.sql` dump to restore the original data state. It then responds to the CLI with a JSON object indicating success or failure. The CLI handles this response and prints the final status to the developer.

# EXAMPLES

**Rollback a production server to a specific backup ID:**
```bash
php spp.php deploy:rollback http://prod.example.com backup_1688123456 --key=my_secret_key
```

**Force an emergency rollback from a CI/CD pipeline script:**
```bash
php spp.php deploy:rollback http://prod.example.com backup_1688123456 -y
```

---

# NAME

deploy:run - Securely execute an arbitrary shell command on the remote server

# SYNOPSIS

`php spp.php deploy:run <target_uri> "<command>" [--key=YOUR_API_KEY]`

# PURPOSE

The `deploy:run` command allows developers to execute arbitrary shell commands directly on a remote server from their local terminal, routing the command securely through the deployment API rather than requiring an SSH session. This is exceptionally useful for running framework-specific CLI tasks (like clearing cache or running migrations) on the remote target.

# OPTIONS AVAILABLE

*   `<target_uri>` (Required)
    The remote environment URI.
*   `"<command>"` (Required)
    The exact shell command you wish to execute. It should be wrapped in quotes to prevent local shell expansion.
*   `--key=YOUR_API_KEY` (Optional)
    The API token to authenticate the request.

# UNDER THE HOOD ACTIVITY

The command parses the CLI arguments, ensuring that both a target URI and the shell command string are provided. It then initializes the `TargetConnection::resolve()` HTTP handler.

The CLI calls `$conn->runCommand($commandToRun)`. This encapsulates the shell string into a secure API payload and transmits it to the remote deployment receiver. 

On the remote server, the deployment receiver (if configured to allow arbitrary remote execution) utilizes PHP's system execution functions (such as `exec()` or `proc_open()`) to execute the provided string within the remote machine's shell context. It captures the standard output, standard error, and the exit code of the process.

The remote server packages this data and sends it back to the local CLI. The CLI then prints the standard output verbatim to the terminal. Finally, it evaluates the returned `exit_code`. If the exit code is not `0` (indicating an error at the OS level), it outputs a warning highlighting the non-zero exit code. Otherwise, it prints a success confirmation.

# EXAMPLES

**Clear the cache on the remote production server:**
```bash
php spp.php deploy:run http://prod.example.com "php spp.php cache:clear" --key=my_key
```

**Check the disk space on the remote server:**
```bash
php spp.php deploy:run http://prod.example.com "df -h"
```

---

# di:list

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

---

# diff:apply

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

---

# diff:compare

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

---

# diff:history

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

---

# diff:rollback

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

---

# docs:build

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

---

# drishyam:clear

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

---

# drishyam:compile

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

---

# drishyam:theme:check

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

---

# ent:edit

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
If no arguments are provided, it forces an interactive wizard by listing available entities from `SPPEntity::listAvailableEntities()` and taking user input via a `prompt()` wrapper. It locates the entity config via `SPPEntity::getEntityConfigFile()`. It parses the source YAML, allowing either flag-driven arrays manipulations or a deeply nested interactive CLI loop. Through CLI flags, it interprets the parameters (like auto-computing many-to-many pivot tables logic) and dynamically mutates the YAML array structure. It serializes and persists the updated state strictly via `\SPPMod\SPPDB\SPPEntity::saveEntityDefinition()`.

## EXAMPLES
```bash
php spp.php ent:edit Student --table=new_students --add-field="graduation_year:int"
php spp.php ent:edit User --add-relation="Role:ManyToMany:user_id:user_role"
```

---

# entity:crud

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

---

# env:backup

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

---

# env:get

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

---

# env:set

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

---

# env:status

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

---

# env:token:rotate

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

---

# event:dispatch

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

---

# event:fire

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

---

# event:list-listeners

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

---

# ext:disable

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

---

# ext:enable

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

---

# ext:install

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

---

# ext:list

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

---

## `forge`

**Purpose**: Unified automation and LiveSync engine

### Synopsis
```bash
php spp.php forge [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPP\Core\ModuleCompiler, \SPP\Core\VersionManager, MakeUXComponentCommand, \RecursiveIteratorIterator, \RecursiveDirectoryIterator, module\n, UX.


---

## `form:crud`

**Purpose**: Manage SPP forms (list, create, edit, delete)

### Synopsis
```bash
php spp.php form:crud [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `frontend:debug`

**Purpose**: Toggle Frontend CDN development mode (on|off)

### Synopsis
```bash
php spp.php frontend:debug [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `generate`

**Purpose**: AI Copilot: Generate an entire application feature from a natural language prompt.

### Synopsis
```bash
php spp.php generate [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `group:create`

**Purpose**: Create a new shared resource group

### Synopsis
```bash
php spp.php group:create [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php group:create <group_name> [--extends=core] [--prefix=...]

```

### Options Available
- `--extends=` : Expects a value. Extracted via static analysis from GroupCreateCommand.php.
- `--prefix=` : Expects a value. Extracted via static analysis from GroupCreateCommand.php.
- `--shared_groups` : Boolean flag or option. Extracted via static analysis from GroupCreateCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: shared.


---

## `group:delete`

**Purpose**: Delete a shared resource group

### Synopsis
```bash
php spp.php group:delete [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php group:delete <group_name>

```

### Options Available
- `--shared_group` : Boolean flag or option. Extracted via static analysis from GroupDeleteCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `group:edit`

**Purpose**: Edit an existing shared resource group

### Synopsis
```bash
php spp.php group:edit [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php group:edit <group_name> [--extends=...] [--prefix=...]

```

### Options Available
- `--extends=` : Expects a value. Extracted via static analysis from GroupEditCommand.php.
- `--prefix=` : Expects a value. Extracted via static analysis from GroupEditCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `group:list`

**Purpose**: List all shared resource groups

### Synopsis
```bash
php spp.php group:list [OPTIONS]
```

### Options Available
- `--entities` : Boolean flag or option. Extracted via static analysis from GroupListCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `i18n:export`

**Purpose**: Export translations for a specific locale to a JSON file.

### Synopsis
```bash
php spp.php i18n:export [OPTIONS]
```

### Options Available
- `--locale=` : Expects a value. Extracted via static analysis from I18nExportCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `i18n:import`

**Purpose**: Import translations from a JSON file into the database.

### Synopsis
```bash
php spp.php i18n:import [OPTIONS]
```

### Options Available
- `--locale=` : Expects a value. Extracted via static analysis from I18nImportCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `import:component`

**Purpose**: Imports pristine air-gapped sovereign UI components

### Synopsis
```bash
php spp.php import:component [OPTIONS]
```

### Options Available
- `--target=` : Expects a value. Extracted via static analysis from ImportComponentCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `interdb:config`

**Purpose**: Get or set the interdb operating mode

### Synopsis
```bash
php spp.php interdb:config [OPTIONS]
```

### Options Available
- `--mappings` : Boolean flag or option. Extracted via static analysis from InterdbConfigCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `interdb:mapping:add`

**Purpose**: Add a new InterDB mapping

### Synopsis
```bash
php spp.php interdb:mapping:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php interdb:mapping:add <alias> <engine> <table>

```

### Options Available
- `--mappings` : Boolean flag or option. Extracted via static analysis from InterdbMappingAddCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: InterDB.


---

## `interdb:mapping:list`

**Purpose**: List all InterDB mappings

### Synopsis
```bash
php spp.php interdb:mapping:list [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `interdb:mapping:remove`

**Purpose**: Remove an InterDB mapping

### Synopsis
```bash
php spp.php interdb:mapping:remove [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php interdb:mapping:remove <alias>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `lang:list`

**Purpose**: List all translations

### Synopsis
```bash
php spp.php lang:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from LangListCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: spplang.
- Bootstraps a full application execution context via Scheduler.


---

## `lang:scan`

**Purpose**: Scan directories for new translation keys

### Synopsis
```bash
php spp.php lang:scan [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from LangScanCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: spplang.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: translation.


---

## `lang:set`

**Purpose**: Set a translation for a key

### Synopsis
```bash
php spp.php lang:set [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php lang:set <key> <locale> <translation>

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from LangSetCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: spplang.
- Bootstraps a full application execution context via Scheduler.


---

## `lekhak:generate-docs`

**Purpose**: Generates documentation nodes for SPP Core and Modules.

### Synopsis
```bash
php spp.php lekhak:generate-docs [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: DocGen, LekhakNode.


---

## `lekhak:setup`

**Purpose**: Initializes Lekhak CMS database tables.

### Synopsis
```bash
php spp.php lekhak:setup [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPDB.


---

## `list`

**Purpose**: Lists all discovered SPP CLI commands.

### Synopsis
```bash
php spp.php list [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `live:status`

**Purpose**: Check the status of websocket/polling servers

### Synopsis
```bash
php spp.php live:status [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from LiveStatusCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `live:trigger`

**Purpose**: Push a live event to clients

### Synopsis
```bash
php spp.php live:trigger [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php live:trigger --channel=<channel> --event=<event> [--payload=<json>]

```

### Options Available
- `--channel=` : Expects a value. Extracted via static analysis from LiveTriggerCommand.php.
- `--event=` : Expects a value. Extracted via static analysis from LiveTriggerCommand.php.
- `--payload=` : Expects a value. Extracted via static analysis from LiveTriggerCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `logger:clear`

**Purpose**: Clear the SPP application logs

### Synopsis
```bash
php spp.php logger:clear [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `logger:tail`

**Purpose**: Tail the SPP application log file

### Synopsis
```bash
php spp.php logger:tail [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

# NAME
`make:app` - Create a new SPP application context

# SYNOPSIS
`php spp.php make:app [app_name] [app_type] [base_url] [table_prefix] [--enterprise]`

# PURPOSE
The `make:app` command is used to bootstrap a new SPP application context, providing a fully functional directory structure, environment configurations, and entry points. It supports various frontend paradigms ranging from native PHP, React, Vue, to SPP's own Blade and UX engines, or even a headless Drupal integration.

# OPTIONS AVAILABLE
- `[app_name]` (string): The name of the new application context (e.g., `dashboard`, `api`). If omitted, the CLI will prompt for it interactively.
- `[app_type]` (string): The architecture pattern to scaffold. Available options are `native`, `blade`, `react`, `vue`, `drupal`, `sppux`, and `dropin`. Defaults to `native`. If omitted, prompts interactively.
- `[base_url]` (string): The base URL route for the application (e.g., `/dashboard`). If omitted, it defaults to `/{app_name}` and prompts interactively.
- `[table_prefix]` (string): The prefix for database tables specific to this app. Defaults to `{app_name}_` and prompts interactively.
- `--enterprise` (flag): Enables Enterprise Mode. Configures the app to use Redis for caching and session management. If omitted and the command lacks arguments, it prompts interactively.

# UNDER THE HOOD ACTIVITY
When the `make:app` command is invoked, it sequentially performs several backend provisioning tasks to set up the application environment:
1. **Directory Provisioning**: It creates a standardized directory tree under `SPP_APP_DIR/etc/apps/{app_name}` (for forms and config) and `SPP_APP_DIR/src/{app_name}` (for controllers, services, events, etc.), as well as a view folder under `resources/{app_name}/views`.
2. **Global Settings Registration**: It parses the `spp/etc/global-settings.yml` file and injects the new application's configuration block. This includes routing data, base URL, table prefixes, and if Enterprise mode is enabled, overrides the default cache and session handlers to use a local Redis instance (`tcp://127.0.0.1:6379`).
3. **Routing & Events**: It writes a default `pages.yml` in the app's `etc` directory establishing an `index` route, and creates a stub `events.yml` to lay the groundwork for event-driven handlers (like `BootHandler`).
4. **Context Scaffolding**: Based on the selected `app_type`, it generates the corresponding boilerplate. 
   - For `native`, it creates a bare-bones PHP entry script loading `\SPP\App`.
   - For `blade`, it chains execution directly to `make:blade-project` to scaffold a fully integrated Blade environment.
   - For `sppux`, it writes a complex, reactive `main.js` web component along with a stylish, glassmorphic HTML entry file pre-wired with the SPP-UX JS runtime and simulated admin bridges.
   - For `dropin`, it generates an HTML view template and automatically builds a matching `contact.yml` low-code form, wiring them together via an auto-detecting `ViewPage::processForms()` router.
   - For `react` and `vue`, it drops in the basic HTML structure, CDNs (for Vue) or Module loaders (for React), and starter `.jsx`/`.vue` files.
   - For `drupal`, it scaffolds a stub entry point designed to operate as an integrated backend.

# EXAMPLES
**1. Scaffold a React application interactively:**
```bash
php spp.php make:app frontend react /app front_
```

**2. Scaffold an Enterprise SPP-UX application:**
```bash
php spp.php make:app admin sppux /admin adm_ --enterprise
```

---

# NAME
`make:blade` - Scaffold a new Blade template (Drishyam Paradigm)

# SYNOPSIS
`php spp.php make:blade <ViewName> [--name=<ViewName>]`

# PURPOSE
The `make:blade` command generates a new SPP Blade view file specifically tailored for the Drishyam Paradigm. This creates a visually stylized starter template integrating SPP's internal Blade compilation engine.

# OPTIONS AVAILABLE
- `<ViewName>` or `--name=<ViewName>` (string): The name of the Blade view you wish to create. It automatically ensures the `.blade.php` extension is appended if not provided.

# UNDER THE HOOD ACTIVITY
When executed, this command reads the current execution context via `getContext()` to determine which app namespace to target (e.g. `default`, `admin`). It resolves the view folder to `SPP_APP_DIR/resources/views/<context>/`. If the `<ViewName>.blade.php` file does not exist, it forcefully creates the directory structure. 
It writes a predefined Drishyam layout scaffold containing basic HTML and CSS boilerplate, an `app` layout extension directive (`@extends('layouts.app')`), and specific hero banner code styled with a linear-gradient and inter fonts. The resulting file acts as a standalone interactive view with a placeholder JavaScript alert wired to a primary button.

# EXAMPLES
**1. Scaffold a dashboard view:**
```bash
php spp.php make:blade dashboard
```

**2. Scaffold a dashboard view explicitly:**
```bash
php spp.php make:blade --name=dashboard
```

---

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

---

# NAME
`make:blade-scaffold` - Create a full stack Blade scaffold (Entity, YAML Form, Controller, Blade Views)

# SYNOPSIS
`php spp.php make:blade-scaffold [EntityName]`

# PURPOSE
The `make:blade-scaffold` command is the ultimate Rapid Application Development (RAD) tool within SPP. Given an entity name, it generates a complete vertical slice of functionality: Database Entity configuration, UI Form generation via YAML, Blade index and edit views, and the PHP logic entry point required to run the CRUD operations.

# OPTIONS AVAILABLE
- `[EntityName]` (string, optional): The name of the data model you wish to scaffold (e.g. `Student`, `Product`). If omitted, the CLI will prompt for it interactively.

# UNDER THE HOOD ACTIVITY
This command handles a complete MVC generation lifecycle interactively:
1. **Interactive Prompting**: Prompts for `Entity Name`, `App Name (Context)` (defaults to current context), and `Table Name` (defaults to the plural, lowercase entity name).
2. **Entity Definition**: Uses `\SPPMod\SPPDB\SPPEntity::saveEntityDefinition()` to physically write a new entity schema configuration for the requested context, defaulting to standard fields like `id`, `name` (varchar), and `description` (text).
3. **YAML Form Generation**: Generates a standard Create/Update form configuration saved to `etc/apps/{app_name}/forms/{entity}.yml`. The form embeds `SPPText` and `SPPTextArea` inputs and sets up automatic form submissions linked to the newly generated entity.
4. **Blade View Synthesis**: Writes both `index.blade.php` (a tabular list view rendering `$items`) and `form.blade.php` (incorporating `@@sppform` and `@@sppbind` directives). It also creates a generic `app.blade.php` layout if it doesn't already exist.
5. **Entry Point Provisioning**: Constructs a standalone `{app_name}_{entity}.php` file at the root. This script initializes the SPP environment, determines the requested action (list, create, edit), uses the ORM (e.g., `\SPPMod\SPPEntity\Product::find($id)`) to fetch records, and defines a `{entity}_form_submitted` callback to intercept POST requests, magically populating the model via `$item->loadFromArray($_POST)` and calling `$item->save()`. Finally, it executes `processForms()` and renders the correct Blade view based on state.

# EXAMPLES
**1. Scaffold a 'Product' CRUD system:**
```bash
php spp.php make:blade-scaffold Product
```
*(Proceed through interactive prompts for Table Name and Context).*

---

# NAME
`make:command` - Create a new CLI command class

# SYNOPSIS
`php spp.php make:command <name> [--app=appname] [--command=cmd:name]`

# PURPOSE
The `make:command` utility rapidly scaffolds a new PHP CLI command class that integrates seamlessly into the SPP task runner. It handles namespace resolution, class naming conventions, and generates the necessary base execution logic, ensuring the new command is immediately discoverable by the CLI runtime.

# OPTIONS AVAILABLE
- `<name>` (string, required): The target name of the class (e.g. `ClearCache`). If the word "Command" is omitted, it will automatically append it to the class name (e.g. `ClearCacheCommand`).
- `--app=<appname>` (string, optional): The execution context/app where this command should be bound. Affects namespace and target directories.
- `--command=<cmd:name>` (string, optional): Overrides the actual CLI execution signature (the string typed after `php spp.php`). Defaults to the lowercase version of `<name>`.

# UNDER THE HOOD ACTIVITY
When invoked, the command resolves the target directory mapping using `getTargetDir()` on the `commands` folder. It standardizes the class name string via `ucfirst()`. The command explicitly searches for the `--command=` flag in the arguments array to assign the `$name` property of the new command object. Finally, it delegates the actual file generation to `buildFromStub()`, merging the `namespace`, `className`, `commandName`, and a placeholder description into the predefined `command` stub file. The newly created command is automatically picked up via the `SPP\CLI\CommandManager` namespace auto-discovery mechanism.

# EXAMPLES
**1. Scaffold a database backup command:**
```bash
php spp.php make:command BackupDb --command=db:backup
```

---

# NAME
`make:controller` - Create a new controller class

# SYNOPSIS
`php spp.php make:controller <name> [--app=appname] [--resource]`

# PURPOSE
The `make:controller` command provisions a new PHP controller class utilized to handle HTTP routing and request processing logic. This creates a standardized skeleton that connects URLs to specific PHP methods seamlessly within the SPP MVC lifecycle.

# OPTIONS AVAILABLE
- `<name>` (string, required): The core name of the controller. "Controller" will be automatically appended if missing (e.g. `User` becomes `UserController`).
- `--app=<appname>` (string, optional): Specify the application context namespace (resolving to `src/{app_name}/controllers/`).
- `--resource` (flag, optional): Indication flag. Note: The current execution stub parses this argument but currently delegates the actual implementation entirely to the base `controller` stub.

# UNDER THE HOOD ACTIVITY
Upon execution, it retrieves the current application context and transforms the provided name via `ucfirst()`, ensuring `Controller` is suffixed to the string. The file creation explicitly generates a prefix style filename: `class.{lowercase_controller_name}.php`. It extracts a `$routeName` variable by stripping the "Controller" suffix from the name and converting it to lowercase, which is injected into the stub to provide a default route path. The actual code generation utilizes `buildFromStub('controller', ...)` mapping the `namespace`, `className`, and `routeName`. The CLI output also includes a unique `renderAdminUI()` function, which dynamically renders an HTML-based interactive form bridging the web-based Administrator Console to the CLI utility using JavaScript `window.executeCommand`.

# EXAMPLES
**1. Scaffold an Auth controller:**
```bash
php spp.php make:controller Auth --app=admin
```

---

# NAME
`make:deployment` - Generate Enterprise Docker and Kubernetes scaffolding for the application

# SYNOPSIS
`php spp.php make:deployment [app_name] [--with-redis] [--host=IP] [--user=user] [--key=id_rsa]`

# PURPOSE
The `make:deployment` command builds out an exhaustive containerized deployment scaffold allowing an SPP app to run in enterprise environments. It generates customized NGINX, PHP-FPM, MariaDB, and optional Redis orchestration configs via Docker Compose, as well as providing automated SSH-based remote push capabilities.

# OPTIONS AVAILABLE
- `[app_name]` (string): The target app to deploy. Defaults to `default`.
- `--with-redis` (flag): Dynamically injects a Redis container dependency into the `docker-compose.yml` for high-performance session and cache handling.
- `--host=<IP>` (string): The remote server IP for automated SCP deployment.
- `--user=<username>` (string): The SSH user for the remote server.
- `--key=<key_path>` (string): The path to the SSH private key used for remote deployment.

# UNDER THE HOOD ACTIVITY
Executing this command generates a `deploy/{app_name}` directory at the root of the project. It explicitly builds out four critical infrastructural files:
1. `Dockerfile`: Pulls `php:8.2-fpm-alpine`, installs core extensions (PDO, MySQL, SQLite, OPcache, Redis), copies configs, and sets a custom `CMD` that forcibly triggers `php spp.php sppmigrate:run` before spawning `supervisord` to ensure the database schema is built dynamically on boot.
2. `docker-compose.yml`: Scaffolds a multi-container network. The `app` service is bound to port 8080 mapping to 80 internally. A MariaDB `db` service is generated with persistent volumes. If `--with-redis` is true, string replacements dynamically inject a `redis:7-alpine` service.
3. `nginx.conf`: Hardcodes an optimized NGINX config explicitly proxying `.php` files to local port 9000 using `fastcgi`.
4. `supervisord.conf`: Creates a process monitor config that simultaneously runs `php-fpm`, `nginx`, and a background worker running `php spp.php queue:work` indefinitely.

If `--host` and `--user` are defined, it alters behavior entirely, compiling a `push.sh` bash script. This script automatically archives the entire repo using `tar -czf`, sends it via `scp`, extracts it on the remote server into `/opt/spp/{app_name}`, and triggers a headless `docker-compose up -d --build`.

# EXAMPLES
**1. Local containerization with Redis:**
```bash
php spp.php make:deployment dashboard --with-redis
```

**2. Direct Push to Production Server:**
```bash
php spp.php make:deployment api --host=192.168.1.100 --user=root --key=~/.ssh/id_rsa
```

---

# NAME
`make:dotnet-service` - Create a new .NET service project

# SYNOPSIS
`php spp.php make:dotnet-service <name> [--app=context]`

# PURPOSE
The `make:dotnet-service` command scaffolds an external microservice written in C# .NET Core. It automatically provisions a new C# Console Application that binds to the `SppClient` library, allowing native inter-process communication or external execution capabilities alongside the core SPP PHP framework.

# OPTIONS AVAILABLE
- `<name>` (string, required): The target name of the .NET Service.
- `--app=<context>` (string, optional): The application context namespace.

# UNDER THE HOOD ACTIVITY
When triggered, the PHP CLI executes actual OS-level `.NET` commands via `shell_exec()`. It locates the target directory under `services/dotnet/service.{lowercase_name}`. 
1. It runs `dotnet new console -n Service.{Name} -o {projectDir}` to bootstrap a raw .NET C# console application. The project name is escaped using `escapeshellarg()` to prevent command injection.
2. It then computes the absolute path to the SPP base directory and runs `dotnet add {projectDir} reference {SppClient.csproj}`. This physically alters the C# project file to depend on SPP's internal C# interop library (`SppClient`).
3. Finally, it uses the standard SPP `buildFromStub()` mechanism to delete the default C# `Program.cs` and replace it with a highly specialized `dotnet_service` stub, injecting the `CLASS_NAME` directly into the C# source code.

# EXAMPLES
**1. Scaffold an Image Processing microservice in C#:**
```bash
php spp.php make:dotnet-service ImageProcessor
```

---

# NAME
`make:drupal-bridge` - Scaffold a Drupal module to bridge SPP into Drupal

# SYNOPSIS
`php spp.php make:drupal-bridge [drupal_root_path]`

# PURPOSE
The `make:drupal-bridge` command constructs a custom Drupal module (`spp_bridge`) designed to inextricably link a headless or monolithic Drupal instance (versions 8 through 11) with the SPP framework. It effectively injects SPP services, ORM entities, and authentication states directly into Drupal's Twig rendering engine and PHP execution context.

# OPTIONS AVAILABLE
- `[drupal_root_path]` (string, optional): The relative path from the SPP installation to the root of the Drupal project. If not provided, it falls back to an interactive CLI prompt.

# UNDER THE HOOD ACTIVITY
This command programmatically generates a standard Drupal module schema in the specified Drupal root's `/modules/custom/spp_bridge` directory. 
1. **Module Info**: It creates `spp_bridge.info.yml` configuring compatibility for Drupal cores `^8 || ^9 || ^10 || ^11`.
2. **Bootstrapper**: It builds a `spp_bridge.module` file. This `.module` file aggressively loads SPP's absolute path `sppinit.php` Autoloader, fully booting the SPP framework inside the Drupal request lifecycle. It also registers a `hook_twig_functions` or `theme_suggestions_alter`, providing an explicit example of how to alter Drupal's rendered output based on `\SPPMod\SPPAuth\SPPAuth::isLoggedIn()`.
3. **Twig Services**: It writes `spp_bridge.services.yml` configuring a new Drupal tagged service (`twig.extension`).
4. **Twig Extension Class**: It provisions `SPPBridgeExtension.php` mapping custom Twig functions like `spp_entity(entity, id)` and `spp_service(service, method, params)` to the static methods of `\SPPMod\SPPDrupal\SPPDrupalBridge`. This immediately allows Drupal frontend developers to natively query SPP data directly inside Drupal `.html.twig` templates.

# EXAMPLES
**1. Scaffold a bridge module assuming Drupal is in a parallel folder:**
```bash
php spp.php make:drupal-bridge ../drupal_site
```

---

# NAME
`make:entity` - Create a new SPPEntity definition

# SYNOPSIS
`php spp.php make:entity [EntityName] [--app=AppName] [--table=TableName] [--extends=Class] [--login=true|false] [--fields="f1:type,f2"] [--relations="Rel"] [--api] [--resource]`

# PURPOSE
The `make:entity` command is a powerful data-modeling tool. It dynamically generates structural configurations for an SPPEntity instance, mapping object-oriented models to underlying relational databases. It supports both interactive wizard workflows and inline CLI argument execution for complex, CI/CD-friendly schema generation.

# OPTIONS AVAILABLE
- `[EntityName]` (string): The logical name of the entity (e.g. `Student`).
- `--app=<AppName>` (string): The application context context. Defaults to interactive prompt or `default`.
- `--table=<TableName>` (string): Explicitly override the default database table. If not provided, it resolves to the plural lowercase representation of the Entity Name (e.g., `students`).
- `--extends=<Class>` (string): Establish PHP class inheritance for the entity object (e.g., `\App\Entities\User`).
- `--login=<true|false>` (boolean): If `true`, injects SPP Login Support directly into this specific entity layer.
- `--fields="<name:type>"` (string): Comma-separated list of attributes. Defaults to `varchar(255)` if the type is omitted. (e.g., `--fields="name:varchar(255),age:int"`).
- `--relations="<Target:Type:ForeignKey:Pivot>"` (string): A comma-separated list of database relations. Types include `OneToMany` and `ManyToMany`. 
- `--api`, `--resource` (flag): Dynamically scaffolds a boilerplate RESTful JSON controller mapping CRUD methods (`index`, `show`, `store`, `update`, `destroy`) to the newly built entity in `src/{app_name}/controllers/api/`.

# UNDER THE HOOD ACTIVITY
The command checks if it needs to trigger the Interactive Wizard (if arguments like `--fields`, `--extends`, etc., are missing) or if it operates in Headless mode. 
It compiles an extensive schema `$config` array mapping `table`, `id_field`, `sequence`, `extends`, `login_enabled`, `attributes`, and `relations`. For relations, it specifically identifies `ManyToMany` declarations to automatically guess pivot table names (e.g., `student_course`).
It executes `\SPPMod\SPPDB\SPPEntity::saveEntityDefinition()`, directly writing the YAML or serialized schema to disk.
If the `--api` or `--resource` flags are detected, it hooks into standard file manipulation, constructing a `.php` file in the API controllers directory implementing the `\SPP\Core\ResourceController` interface, and wires it to `\SPPMod\SPPEntity\SPPEntity` methods.

# EXAMPLES
**1. Execute the Interactive Wizard:**
```bash
php spp.php make:entity Student
```

**2. Non-interactive CLI Scaffold with API:**
```bash
php spp.php make:entity Course --table=spp_courses --fields="title:varchar(100),credits:int" --relations="\App\Entities\Student:ManyToMany:course_id" --api
```

---

# NAME
`make:event` - Create a new event entry and scaffold its handler

# SYNOPSIS
`php spp.php make:event <EventName> <HandlerClassName> [--app=appname] [--overridable] [--default-handler]`

# PURPOSE
The `make:event` command provisions event-driven architectural components. It physically creates a new Event Handler PHP class and registers the execution mapping within the application's `events.yml` configuration map, bridging the custom handler logic to the system event bus.

# OPTIONS AVAILABLE
- `<EventName>` (string, required): The target string representing the event trigger (e.g. `user.registered`).
- `<HandlerClassName>` (string, required): The target handler class name (e.g. `SendWelcomeEmail`).
- `--app=<appname>` (string, optional): The application context. (Requires a specific app namespace; errors on `default`).
- `--overridable` (flag, optional): Modifies `events.yml` to set the event as overridable.
- `--default-handler` (flag, optional): Registers the HandlerClassName as the `default_handler` inside the config instead of pushing it to the `listeners` array.

# UNDER THE HOOD ACTIVITY
The command invokes `buildFromStub('eventhandler', ...)` to generate the skeleton class `{HandlerClassName}.php` inside the designated app's `events` directory. 
After class generation, it targets `SPP_APP_DIR/src/{app}/etc/events.yml`. It parses the file using the `Symfony\Component\Yaml\Yaml` component. It intricately handles schema upgrades: if the existing event is a simple array but flags like `--overridable` or `--default-handler` are utilized, it dynamically refactors the array into a complex object containing a `listeners` array.
The fully qualified class name (e.g., `\App\Admin\Events\SendWelcomeEmail`) is subsequently injected into the `events.yml` structure.
Finally, to ensure the SPP event bus recognizes the modification immediately, it utilizes `shell_exec()` to trigger the `cache:clear` CLI command, wiping the framework's internal routing/event cache.

# EXAMPLES
**1. Scaffold a user registration event:**
```bash
php spp.php make:event user.created UserCreatedHandler --app=portal
```

---

# NAME
`make:eventhand` - Create a new Event Handler class

# SYNOPSIS
`php spp.php make:eventhand <HandlerClassName> [--app=appname]`

# PURPOSE
The `make:eventhand` command specifically scaffolds an unlinked Event Handler PHP class. Unlike `make:event`, it *does not* register the class in the `events.yml` configuration map, allowing developers to manually link complex event topologies.

# OPTIONS AVAILABLE
- `<HandlerClassName>` (string, required): The target name for the PHP Class.
- `--app=<appname>` (string, optional): The application context namespace.

# UNDER THE HOOD ACTIVITY
It resolves the target `Events` namespace based on the requested application context. If context is explicitly `default`, the namespace degrades to `EventHandlers`. It invokes the `buildFromStub()` compiler passing the `eventhandler` stub format, generating `{HandlerClassName}.php`.
Once generation concludes, it programmatically triggers a system cache flush via an asynchronous `shell_exec("php spp.php cache:clear")` invocation to guarantee namespace auto-discovery.

# EXAMPLES
**1. Generate an isolated handler:**
```bash
php spp.php make:eventhand AuditLogger --app=api
```

---

# NAME
`make:form` - Create a new SPP form definition

# SYNOPSIS
`php spp.php make:form <name> [--app=appname]`

# PURPOSE
The `make:form` command provisions a specialized PHP Form Controller skeleton. This controller structure is specifically tailored to process front-end user interactions, manage form validation states, and implement business logic upon submission.

# OPTIONS AVAILABLE
- `<name>` (string, required): The core name of the form (e.g. `Contact`). "Form" will be appended automatically if excluded.
- `--app=<appname>` (string, optional): Determines the target execution context.

# UNDER THE HOOD ACTIVITY
The command normalizes the input class name with `ucfirst()`, validating and enforcing a `Form` suffix. It resolves the absolute file path resolving to `class.{lowercase_form_name}.php` within the `forms` directory of the targeted application context.
It calculates a specific `$formRoute` (removing the Form suffix) that serves as the logical identifier. The generation utilizes the `form` stub format mapping the namespace, class structure, and specific routing identifiers directly into the file.
Furthermore, the CLI includes a UI bridge via `renderAdminUI()`. This method outputs raw HTML strings to allow visual generation of the form directly from the SPP web-based command console, passing parameters via JS to the `window.executeCommand()` global function.

# EXAMPLES
**1. Scaffold a User Login form:**
```bash
php spp.php make:form UserLogin --app=frontend
```

---

## `make:go-service`

**Purpose**: Create a new Go service script

### Synopsis
```bash
php spp.php make:go-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:go-service <name> [--app=context]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: Go.


---

## `make:java-service`

**Purpose**: Create a new Java service script

### Synopsis
```bash
php spp.php make:java-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:java-service <name> [--app=context]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: Java.


---

# NAME
`make:live-component` - Create a new Live Component class

# SYNOPSIS
`php spp.php make:live-component <name> [--app=appname]`

# PURPOSE
The `make:live-component` command provisions a PHP class for the SPPLive subsystem (conceptually similar to Livewire). These components enable complex, interactive, and reactive server-side state mutations transmitted dynamically to the frontend without requiring custom JavaScript implementations.

# OPTIONS AVAILABLE
- `<name>` (string, required): The target name of the component (e.g. `UserSearch`, `InteractiveTable`).
- `--app=<appname>` (string, optional): Determines the target execution context.

# UNDER THE HOOD ACTIVITY
Upon execution, it validates the name, normalizes it using `ucfirst()`, and retrieves the relevant application context via `getContext()`.
It targets the `live` directory within the application context, naming the file explicitly as `class.{lowercase_component_name}.php`.
The `buildFromStub()` compiler is executed using the `livecomponent` stub format, writing the properly namespaced class to disk.
Furthermore, this command includes the internal `renderAdminUI()` function, empowering administrators to invoke this command through a visual HTML interface via the SPP Admin Panel utilizing the `window.executeCommand` JavaScript bridge.

# EXAMPLES
**1. Create a reactive search component:**
```bash
php spp.php make:live-component SearchFilter --app=frontend
```

---

# NAME
`make:middleware` - Create a new middleware class

# SYNOPSIS
`php spp.php make:middleware <name> [--app=appname]`

# PURPOSE
The `make:middleware` command rapidly scaffolds an HTTP Middleware class. Middleware acts as a filtering layer intercepting requests before they hit controllers or intercepting responses before they are returned to the client, commonly utilized for authentication, CORS, or logging.

# OPTIONS AVAILABLE
- `<name>` (string, required): The logical name of the middleware (e.g. `RequireAuth`, `RateLimiter`).
- `--app=<appname>` (string, optional): Determines the target execution context namespace.

# UNDER THE HOOD ACTIVITY
It normalizes the provided name and isolates the application context via `getContext()`. It calculates the fully qualified `Middleware` namespace and targets the `middleware` subfolder, ensuring the PHP file is prefixed as `class.{lowercase_middleware_name}.php`.
The command utilizes the internal `buildFromStub()` generator against the `middleware` stub configuration, dynamically populating the namespace and class structure. It advises the user post-generation to explicitly register the new middleware within the `spp/etc/middleware.yml` mapping file or app-specific configurations.

# EXAMPLES
**1. Scaffold a rate limiting middleware:**
```bash
php spp.php make:middleware ThrottleRequests --app=api
```

---

# NAME
**make:migration** - Create a new database migration file

# SYNOPSIS
`php spp.php make:migration <migration_name>`

# PURPOSE
Generates a boilerplate PHP migration class file within the current application context. This file is used to define structural changes to the database schemas such as creating or modifying tables.

# OPTIONS AVAILABLE
- `<migration_name>` : **Required.** A descriptive name for the migration. E.g., `create_users_table`.

# UNDER THE HOOD ACTIVITY
The script begins by asserting that the `<migration_name>` argument is provided. It then determines the active module or application context via `\SPP\Scheduler::getContext()`. It formats the provided migration name by aggressively converting it to snake_case, and subsequently transforming it into a PascalCase class name (e.g., `CreateUsersTable`). A timestamp format (`Y_m_d_His`) is generated and prepended to the filename to ensure chronological ordering. The CLI then ensures the directory `SPP_APP_DIR/src/<context>/migrations` exists, creating it with `0777` permissions if necessary. Finally, it generates a heredoc PHP template inheriting from `\SPPMod\SPPDB\Migration\SPPMigration` and saves it to the path, outputting the file location in green ANSI text.

# EXAMPLES
Create a migration to add an orders table:
`php spp.php make:migration create_orders_table`

---

# NAME
`make:mixed-paradigm` - Scaffold a Kitchen Sink view blending SPPView, Drishyam, and SPPUX

# SYNOPSIS
`php spp.php make:mixed-paradigm <ViewName> [--name=<ViewName>]`

# PURPOSE
The `make:mixed-paradigm` command represents the ultimate showcase of the SPP rendering pipeline. It generates a "Kitchen Sink" layout demonstrating three discrete layers of rendering co-existing simultaneously: An outer SPPView (Native PHP AST rendering), a middle Drishyam (Blade) compiled fragment, and an inner SPPUX (Reactive JavaScript Web Component) island.

# OPTIONS AVAILABLE
- `<ViewName>` or `--name=<ViewName>` (string, required): The target root name for the generated mixed-paradigm artifacts.

# UNDER THE HOOD ACTIVITY
The command provisions three distinct files concurrently within the requested application context:
1. **The SPPView Wrapper**: Written to `src/{context}/views/{lowercase_name}.php`. This file constructs a literal PHP AST object (`class {name} extends SPPView`). It initializes a `Drishyam` engine to capture the compiled Blade output, then constructs an HTML document using native `$this->html()`, `$this->head()`, and `$this->body()` invocations, passing down variables.
2. **The Blade Fragment**: Written to `resources/views/{context}/fragments/{lowercase_name}_fragment.blade.php`. This file contains raw HTML augmented by Blade directives (`{{ json_encode($data) }}`), isolated explicitly for Drishyam compilation.
3. **The SPPUX Island**: Written to `comp/{Name}Island.js`. This creates a client-side Reactive web component (`class {Name}Island extends BaseComponent`), complete with asynchronous state initialization (`onInit`) and a functional interactive rendering pipeline using lit-html style template literals.

By executing `file_put_contents` across the three respective structural directories simultaneously, it guarantees seamless orchestration where the parent wrapper dynamically imports the Blade string and embeds the client-side `<spp-element>` tag natively.

# EXAMPLES
**1. Scaffold a complex mixed dashboard:**
```bash
php spp.php make:mixed-paradigm ComplexDashboard
```

---

# NAME
`make:model` - Create a new model class (Fluent-ready)

# SYNOPSIS
`php spp.php make:model <name> [--app=appname] [--table=tablename]`

# PURPOSE
The `make:model` command provisions a new standard PHP Model class pre-configured to utilize the SPP Fluent Query Builder ecosystem. This provides a clean, object-oriented abstraction to interact with database tables natively.

# OPTIONS AVAILABLE
- `<name>` (string, required): The root name of the model (e.g. `User`).
- `--app=<appname>` (string, optional): Dictates the application context namespace (resolving to `src/{app_name}/models/`).
- `--table=<tablename>` (string, optional): Binds the model to a specific database table. If omitted, the table name defaults to the pluralized lowercase version of the model name (e.g. `users`).

# UNDER THE HOOD ACTIVITY
Upon execution, it normalizes the class name via `ucfirst()`. It scans the CLI arguments specifically for the `--table=` parameter; if missing, it computes the fallback string by appending an `s` to the lowercase class name.
It targets the `models` directory relative to the resolved application context and structures the output file as `class.{lowercase_name}.php`. 
Using the `buildFromStub()` mechanism against the `model` stub template, it injects the `namespace`, `className`, and `tableName` values directly into the static properties of the newly written PHP file.

# EXAMPLES
**1. Scaffold a fluent-ready User model:**
```bash
php spp.php make:model User --table=spp_users
```

---

# NAME
`make:module` - Create a new SPP module

# SYNOPSIS
`php spp.php make:module <name> [--scope=spp|contrib|app]`

# PURPOSE
The `make:module` command scaffolds the architectural boilerplate required to construct a modular, pluggable application extension within SPP. It constructs the essential module directory, the autoloader manifest (`module.xml`), and the core bootstrap PHP class.

# OPTIONS AVAILABLE
- `<name>` (string, required): The name of the module (e.g. `Blog`, `Forum`).
- `--scope=<spp|contrib|app>` (string, optional): Defines the organizational boundary of the module.
    - `app` (default): Installs into `SPP_APP_DIR/spp/modules/app/`.
    - `spp`: Installs globally into the core framework at `SPP_BASE_DIR/modules/spp/`.
    - `contrib`: Installs into the community plugin directory at `SPP_BASE_DIR/modules/contrib/`.

# UNDER THE HOOD ACTIVITY
It sanitizes the requested module name, aggressively stripping all non-alphanumeric characters using `preg_replace('/[^a-zA-Z0-9]/', '', $name)` and enforcing lowercase. It calculates the absolute target directory based on the `--scope` argument.
It then physically executes `mkdir()` to generate the directory structure. 
First, it generates a robust `module.xml` manifest detailing the module's name, version, description, namespace (`SPPMod\{Name}`), and explicitly maps the autoloader rules referencing the primary class file.
Second, it generates the primary bootstrap class `class.{name}.php` extending `\SPP\SPPObject`, setting up the constructor logic. The CLI also provides a `renderAdminUI()` bridge to allow modular scaffolding directly from the visual SPP admin dashboard.

# EXAMPLES
**1. Create a local application module:**
```bash
php spp.php make:module Forum --scope=app
```

---

# NAME
`make:node-service` - Create a new Node.js service script

# SYNOPSIS
`php spp.php make:node-service <name> [--app=context]`

# PURPOSE
The `make:node-service` command scaffolds a standalone JavaScript/Node.js execution service file. This allows JavaScript to be utilized natively on the server-side as a polyglot microservice within the SPP ecosystem.

# OPTIONS AVAILABLE
- `<name>` (string, required): The specific logic identifier for the JS script.
- `--app=<context>` (string, optional): Determines the target execution namespace.

# UNDER THE HOOD ACTIVITY
The command resolves the application context and constructs a target path of `services/node/service.{lowercase_name}.js`. Using the internal `buildFromStub()` mechanism against the `node_service` stub format, it injects the `CLASS_NAME` natively into the JavaScript source file.

# EXAMPLES
**1. Scaffold a Node.js data worker:**
```bash
php spp.php make:node-service DataWorker --app=default
```

---

# NAME
`make:perl-service` - Create a new Perl service script

# SYNOPSIS
`php spp.php make:perl-service <name> [--app=context]`

# PURPOSE
The `make:perl-service` command scaffolds a `.pl` Perl executable script intended to be consumed as a Polyglot service within the broader SPP application lifecycle, optimal for complex text processing or legacy script integration.

# OPTIONS AVAILABLE
- `<name>` (string, required): The logic identifier for the Perl script.
- `--app=<context>` (string, optional): Determines the target execution namespace.

# UNDER THE HOOD ACTIVITY
It defines the target path resolving to `services/perl/service.{lowercase_name}.pl`. Through the `buildFromStub()` method mapping to the `perl_service` stub, it physically constructs the Perl script on the filesystem, wiring the `$className` into the script's internal logic structures.

# EXAMPLES
**1. Scaffold a Regex Parser in Perl:**
```bash
php spp.php make:perl-service RegexParser --app=utilities
```

---

# NAME
`make:python-service` - Create a new Python service script

# SYNOPSIS
`php spp.php make:python-service <name> [--app=context]`

# PURPOSE
The `make:python-service` command scaffolds a `.py` Python microservice. This bridges SPP PHP application logic with Python's unparalleled ML, data science, and scripting ecosystem using SPP's Polyglot paradigm.

# OPTIONS AVAILABLE
- `<name>` (string, required): The core name of the service logic.
- `--app=<context>` (string, optional): Determines the target execution namespace.

# UNDER THE HOOD ACTIVITY
It defines the target directory `services/python/` mapping to the application context. By leveraging `buildFromStub()` with the `python_service` stub format, it dynamically outputs `service.{lowercase_name}.py`, injecting the `CLASS_NAME` into the boilerplate syntax natively.

# EXAMPLES
**1. Scaffold a Python text analyzer:**
```bash
php spp.php make:python-service TextAnalyzer --app=default
```

---

# NAME
`make:react-component` - Scaffold a new React component (ESM/No-build)

# SYNOPSIS
`php spp.php make:react-component <ComponentName> [--Name=<ComponentName>] [--app=context]`

# PURPOSE
The `make:react-component` command scaffolds an unbundled, raw EcmaScript Module (ESM) version of a React Component. Unlike standard React development requiring Webpack or Vite, this command provisions a `.js` file that relies on modern browser imports (`https://esm.sh/react`), allowing SPP architectures to drop-in React interfaces instantly without compilation pipelines.

# OPTIONS AVAILABLE
- `<ComponentName>` or `--Name=<ComponentName>` (string, required): The target Javascript component name (e.g. `ProfileCard`).
- `--app=<context>` (string, optional): The application context affecting directory resolution.

# UNDER THE HOOD ACTIVITY
The command resolves the application context and isolates the component target directory via `getTargetDir('comp', ...)`. It creates `{ClassName}.js`.
The CLI hardcodes an explicit JS script into the generated file containing `import React from 'https://esm.sh/react';` and defines a functional default exported React component utilizing native `React.createElement()` arrays mapped to a functional hook `useState()`. This explicitly avoids JSX transpilation, ensuring it can be natively interpreted by the browser engine interacting natively with SPP views.

# EXAMPLES
**1. Scaffold a drop-in React chart:**
```bash
php spp.php make:react-component DataChart --app=dashboard
```

---

# NAME
`make:scaffold` - Create a full stack scaffold (Entity, DB, Controller, View)

# SYNOPSIS
`php spp.php make:scaffold [EntityName]`

# PURPOSE
The `make:scaffold` command is an interactive, legacy RAD tool orchestrating the holistic creation of MVC logic tied directly to SPPEntity definitions. Unlike `make:blade-scaffold` which focuses on Blade and YAML integration, this scaffolds programmatic controller logic and a native View skeleton.

# OPTIONS AVAILABLE
- `[EntityName]` (string, optional): The name of the Entity to scaffold. If omitted, triggers interactive mode.

# UNDER THE HOOD ACTIVITY
1. **Interactive Loop**: The command utilizes `fgets(STDIN)` extensively. It polls for `Entity Name`, `Application/Context`, `Database Table`, and traps the user in an infinite attribute creation loop (`Attribute Name`, `Type`) until an empty string is supplied.
2. **Entity Generation**: Utilizing the arrays built in memory, it invokes `\SPPMod\SPPDB\SPPEntity::saveEntityDefinition()` writing the database map configuration to disk.
3. **Controller Scaffolding**: It targets `src/{app_name}/controllers/` and reads a hardcoded external stub file `stubs/scaffold_controller.stub`. It replaces `{appname}`, `{controllerName}`, and `{entityName}` tokens before explicitly saving the file.
4. **View Scaffolding**: It creates `src/{app_name}/views/{entityName}/index.php` embedding a minimal HTML comment and H1 tag.
5. **Hinting**: It reads `global-settings.yml`. Depending on `auto_evolution` status, it advises the user to run `db:sync`.

# EXAMPLES
**1. Scaffold a native Book CRUD:**
```bash
php spp.php make:scaffold Book
```

---

# NAME
`make:seeder` - Create a new Database Seeder class

# SYNOPSIS
`php spp.php make:seeder <SeederName> [--app=appname]`

# PURPOSE
The `make:seeder` command scaffolds a Database Seeder PHP class. Seeders are heavily utilized to programmatically inject mock data, default states, or testing artifacts into the application database structure.

# OPTIONS AVAILABLE
- `<SeederName>` (string, required): The target identifier for the Seeder class. If missing, it prompts interactively.
- `--app=<appname>` (string, optional): Determines the target execution namespace (resolves to `src/{app_name}/seeders/`).

# UNDER THE HOOD ACTIVITY
The command sanitizes the provided string, automatically appending `Seeder` if it does not naturally terminate with it (e.g. `User` -> `UserSeeder`).
It dynamically calculates the directory `src/{app_name}/seeders`, constructing the folder hierarchy forcefully.
It generates a raw PHP string building the `App\Seeders` namespace and a class exposing a public `run(SPPDB $db)` method. A boilerplate `$db->execute_query()` string is heavily commented inline as an architectural example. Finally, it commits the string natively to the file system using `file_put_contents`.

# EXAMPLES
**1. Scaffold an Admin seeder:**
```bash
php spp.php make:seeder AdminUser --app=core
```

---

# NAME
`make:service` - Create a new service class

# SYNOPSIS
`php spp.php make:service <name> [--app=appname] [--lang=python|node|go|dotnet|perl|java]`

# PURPOSE
The `make:service` command provisions a business logic service class. What makes this command exceptionally powerful is its built-in integration with the SPP Polyglot Worker system. It can scaffold standard PHP services or intelligently scaffold microservices in external languages (like Python or Go) while simultaneously generating a perfectly mapped PHP Proxy class to interface with them.

# OPTIONS AVAILABLE
- `<name>` (string, required): The target identifier for the service logic.
- `--app=<appname>` (string, optional): The application namespace (e.g. `school`, `admin`).
- `--lang=<language>` (string, optional): Indicates the target programming language. Valid options include `php` (default), `python`, `node`, `go`, `dotnet`, `perl`, and `java`.

# UNDER THE HOOD ACTIVITY
First, it extracts the target application context and the intended language.
If the `--lang` flag matches an external language (e.g., `python`), it physically instantiates and executes the specific CLI sub-command (e.g., `MakePythonCommand->execute()`), constructing the native `.py` file deep in the `services/python/` folder.
Next, it calculates the absolute path to that newly created external script. It utilizes `buildFromStub()` mapping to the `polyglot_proxy` template, passing the language explicitly (`polyglotLang`) and the absolute path (`polyglotModule`). It generates the PHP Proxy in `src/{context}/services/class.{name}.php`. This PHP Proxy class dynamically translates standard PHP method calls into IPC invocations directed at the external worker.
If no language is specified or `php` is requested, it simply builds a standard PHP Service structure using the `service` stub.

# EXAMPLES
**1. Scaffold a standard PHP service:**
```bash
php spp.php make:service PaymentGateway --app=billing
```

**2. Scaffold a Python service with a PHP Proxy:**
```bash
php spp.php make:service ImageProcessor --lang=python --app=media
```

---

# NAME
`make:sppview` - Scaffold a new native AST SPPView template

# SYNOPSIS
`php spp.php make:sppview <ViewName> [--name=<ViewName>] [--app=context]`

# PURPOSE
The `make:sppview` command creates a View class utilizing SPPView's native Abstract Syntax Tree (AST) methodology. Unlike Drishyam/Blade which parse string templates, SPPView classes programmatically construct HTML elements via PHP methods (e.g., `$this->div()`, `$this->h1()`), ensuring zero parsing overhead and absolute type safety.

# OPTIONS AVAILABLE
- `<ViewName>` or `--name=<ViewName>` (string, required): The View's PHP Class name.
- `--app=<context>` (string, optional): The application namespace mapping to `src/{context}/views/`.

# UNDER THE HOOD ACTIVITY
It sanitizes the string, extracts the context, and isolates the target path explicitly pointing to `src/{context}/views/{lowercase_name}.php`. 
It constructs a hardcoded PHP source block natively establishing the `App\{Context}\Views` namespace, and extending the core `\SPPMod\SPPView\SPPView` system class. It scaffolds a boilerplate `render(array $data)` function implementing `$this->html()`, `$this->head()`, and `$this->body()` wrappers containing nested node arrays. The physical file is forcibly written to disk using `file_put_contents`.

# EXAMPLES
**1. Scaffold a fast programmatic invoice view:**
```bash
php spp.php make:sppview InvoiceRenderer --app=billing
```

---

# NAME
`make:twig` - Scaffold a new Twig template (Drishyam Paradigm)

# SYNOPSIS
`php spp.php make:twig <ViewName> [--name=<ViewName>]`

# PURPOSE
The `make:twig` command scaffolds a `.twig` View template file explicitly formatted to leverage the Drishyam rendering paradigm, seamlessly bridging enterprise Twig syntax with the SPP Framework context ecosystem.

# OPTIONS AVAILABLE
- `<ViewName>` or `--name=<ViewName>` (string, required): The core filename for the view. If it lacks a `.twig` extension, it will be automatically appended.

# UNDER THE HOOD ACTIVITY
When executed, it derives the application target context and resolves the `resources/views/{context}/` directory. 
It writes a hardcoded HEREDOC string heavily styled with custom CSS (`drishyam-container`, `.twig-hero` gradient) utilizing traditional Twig syntax rules like `{% extends "layouts/app.twig" %}`, `{% block title %}`, and `{% block content %}`. It replaces structural tokens (`{{VIEW_NAME}}`, `{{CONTEXT}}`) dynamically prior to forcefully writing the file to disk via `file_put_contents`.

# EXAMPLES
**1. Scaffold a Twig profile view:**
```bash
php spp.php make:twig UserProfile
```

---

# NAME
`make:ux-component` - Scaffold a new SPP-UX reactive component

# SYNOPSIS
`php spp.php make:ux-component <ComponentName> [--template=external]`

# PURPOSE
The `make:ux-component` command scaffolds an SPP-UX specific web component. SPP-UX is an internal, zero-build client-side reactivity engine. This command bridges modern frontend component-based development directly into SPP without requiring Webpack or NPM.

# OPTIONS AVAILABLE
- `<ComponentName>` or `--name=<ComponentName>` (string, required): The Javascript component name.
- `--template=external` (flag, optional): If utilized, it splits the component logically, creating both a logic controller `.js` file and a detached HTML layout file `.html`, wiring them together asynchronously.

# UNDER THE HOOD ACTIVITY
The command resolves the application context, mapping it to `src/{context}/comp/`.
If `--template=external` is omitted, it creates a single `.js` file defining an ES6 class extending `BaseComponent`. This class embeds a complex `lit-html` style template literal directly within its `render()` function, fully styled with deep CSS gradients, interactive roadmap tabs, state conditionals (`${activeTab === 'roadmap' ? ... : ...}`), and an asynchronous `onInit` lifecyle hook utilizing `this.setState()`.
If `--template=external` is supplied, it scaffolds two separate files. The `.html` file contains purely raw CSS/HTML markup. The `.js` file is vastly altered: its `render()` method explicitly returns a `Fragment`, and its `onInit()` method constructs an async `fetch()` request targeting `${this.app.config.baseUrl}/src/{CONTEXT}/comp/{FILE_NAME}.html`, downloading the template and dynamically injecting it as an HTML `<template>` node into the DOM, linking it implicitly to the `BaseComponent` shadow DOM rendering cycle.

# EXAMPLES
**1. Scaffold an inline reactive component:**
```bash
php spp.php make:ux-component DataTable
```

**2. Scaffold an external template component:**
```bash
php spp.php make:ux-component LoginWidget --template=external
```

---

## `make:view`

**Purpose**: Create a new view definition (equivalent to Drupal Views).

### Synopsis
```bash
php spp.php make:view [OPTIONS]
```

### Options Available
- `--table=` : Expects a value. Extracted via static analysis from MakeViewCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: view, \SPPMod\SPPDB\SPPDB.


---

# NAME
`make:vue-component` - Scaffold a new Vue 3 component (ESM/No-build)

# SYNOPSIS
`php spp.php make:vue-component <ComponentName> [--Name=<ComponentName>] [--app=context]`

# PURPOSE
The `make:vue-component` command provisions an unbundled EcmaScript Module (ESM) Vue 3 component explicitly constructed to operate independently of a build process. It leverages raw browser ESM imports to provide Vue's reactive capabilities natively within SPP views.

# OPTIONS AVAILABLE
- `<ComponentName>` or `--Name=<ComponentName>` (string, required): The target Vue component name.
- `--app=<context>` (string, optional): Resolves the target directory relative to the application context.

# UNDER THE HOOD ACTIVITY
The command sanitizes the requested name, sets the target path at `comp/{ClassName}.js`, and forcefully creates the necessary directory tree.
The CLI populates the generated `.js` artifact with a script importing `ref` dynamically from `https://esm.sh/vue`. It exports a default Vue configuration object utilizing the Composition API `setup()` function returning a reactive `count` state. Rather than requiring a `.vue` SFC (Single File Component) compiler, it explicitly maps the component layout into a raw string `template` parameter.

# EXAMPLES
**1. Scaffold an ESM Vue component:**
```bash
php spp.php make:vue-component UserDashboard --app=admin
```

---

# NAME
`man` - Format and display manual pages for SPP commands

# SYNOPSIS
`php spp.php man [COMMAND_NAME]`

# PURPOSE
Provides a built-in terminal-based manual pager (similar to UNIX `man`) for all discovered SPP CLI commands. It can group commands by namespace, list available commands under a specific namespace, or provide deep documentation for a specific command if `getHelp()` is implemented.

# OPTIONS AVAILABLE
- `COMMAND_NAME` : Optional. The specific command or namespace to query (e.g., `cache` or `cache:clear`). If omitted, displays the root command index and namespace groups.

# UNDER THE HOOD ACTIVITY
When invoked, the `man` command utilizes `SPP\CLI\CommandManager::discover()` to dynamically introspect the CLI environment, gathering a list of all registered `Command` classes.
If no argument is passed, it parses the command names, splitting them by the `:` delimiter to aggregate a count of commands per namespace. It maps predefined human-readable descriptions to these namespaces (e.g., `db` maps to 'Database migrations and verifications') and renders a structured, formatted index to standard output.
When a specific prefix (namespace) is queried, it filters the discovered commands to those starting with the given prefix and lists their descriptions.
If an exact command match is found, it instantiates the corresponding `Command` object, retrieves metadata via `getName()`, `getDescription()`, and `getHelp()`, and dynamically generates a bolded, cleanly formatted text manual directly to the terminal. It does not perform any file writing or interact with external data sources; it solely performs reflection-like introspection of the CLI registry.

# EXAMPLES
- `php spp.php man` - Lists all available CLI namespaces and root commands.
- `php spp.php man cache` - Lists all commands within the `cache` namespace.
- `php spp.php man cache:clear` - Displays the full manual page for the `cache:clear` command.

---

# NAME
`man:generate` - Generate highly detailed man-pages in Markdown and UNIX roff formats via static code analysis

# SYNOPSIS
`php spp.php man:generate`

# PURPOSE
Automatically extracts, parses, and statically analyzes the source code of every registered SPP CLI command to generate exhaustive documentation. It outputs individual Markdown files and standard UNIX `roff` formatted manual pages, compiling a complete CLI reference manual.

# OPTIONS AVAILABLE
No options required.

# UNDER THE HOOD ACTIVITY
Upon execution, the command uses `SPP\CLI\CommandManager::discover()` to acquire the full suite of CLI commands. For every command discovered, it leverages PHP's `ReflectionClass` to locate the source code file of the command class. It reads the raw PHP source into memory using `file_get_contents()` and applies an array of complex regular expressions to perform static code analysis.
First, it extracts CLI option flags by searching the Abstract Syntax Tree/code strings for patterns like `str_starts_with`, strict equality checks, `in_array` calls, and array key checks (e.g., `$args['options']`).
Secondly, it scans for known framework APIs to determine side effects. It checks for database interaction (`SPP\DB`, `DB::`), filesystem operations (`file_put_contents`, `mkdir`), execution of system binaries (`exec`, `shell_exec`), module loading (`loadModule`), context switching (`Scheduler::withContext`), HTTP calls (`curl_init`), and caching operations (`Redis::`, `Cache::`).
Based on this heuristic data, it dynamically constructs a comprehensive description of the command's internal behavior. Finally, the tool formats this payload into two formats: an index-linked Markdown document suite saved to `docs/commands/` and standard UNIX `man1` roff files saved to `man/man1/`. A unified table of contents is also written to `docs/spp-cli-manual.md`.

# EXAMPLES
- `php spp.php man:generate` - Scans all commands and generates documentation in the `docs/` and `man/` directories.

---

# NAME
`manifest:export` - Exports tool autodiscovery definitions for AI Copilots

# SYNOPSIS
`php spp.php manifest:export`

# PURPOSE
Synthesizes and exports an AI Copilot autodiscovery manifest (`spp-ai-plugin.json`), enabling external Large Language Models (like OpenAI's GPT) to natively discover, understand, and interact with the SPP framework's operational tools via an OpenAPI schema.

# OPTIONS AVAILABLE
No options required.

# UNDER THE HOOD ACTIVITY
When triggered, the command first validates the existence of the `.well-known` directory within the application root (`SPP_APP_DIR`). If absent, it creates it with `0777` permissions. 
The command then dynamically checks if the SPP AI module is available by verifying the existence of the `\SPPMod\SPPAI\SPPAI` class and its `generateAiManifest` method. If the AI module is installed and active, it delegates the manifest construction to that module, ensuring custom AI definitions are respected. 
If the AI module is unavailable, it gracefully falls back to generating a default JSON object conforming to the standard AI Plugin Schema v1. This default payload defines the model as `SPP_Enterprise_Engine`, describes the system interface, sets authentication type to `none`, maps the OpenAPI definition URL to `/api.php?__manifest=true`, and assigns a logo URL. The resulting JSON payload is directly written to `.well-known/spp-ai-plugin.json` using `file_put_contents()`, making the platform instantly compatible with standard AI plugin discovery protocols.

# EXAMPLES
- `php spp.php manifest:export` - Generates and saves the `.well-known/spp-ai-plugin.json` manifest.

---

# NAME

migrate - Run pending database migrations

# SYNOPSIS

`php spp.php migrate`

# PURPOSE

The `migrate` command is responsible for executing all pending database migrations. It ensures that the database schema is up-to-date with the codebase by running the `up()` methods of any migration classes that have not yet been recorded in the database's migration tracking table.

# OPTIONS AVAILABLE

This command does not accept any specific optional flags or arguments. It relies entirely on the global application context to determine which migrations to run.

# UNDER THE HOOD ACTIVITY

Upon execution, the command determines the active execution environment by calling `\SPP\Scheduler::getContext()`. It prints an initial status message indicating the context for which migrations are being executed.

It then instantiates the `\SPPMod\SPPDB\Migration\SPPMigrationManager`, passing the active context into its constructor. The core logic is delegated to the manager's `$manager->runPending()` method. 

Under the hood, the `SPPMigrationManager` scans the target `db/migrations` directory for PHP class files. It cross-references the filenames found on disk against the internal database tracking table (e.g., `spp_migrations`). For any file not found in the database table, the manager instantiates the migration class, executes its `up()` method (which contains the raw SQL statements or schema builder logic), and then inserts a record into the tracking table to mark the migration as complete.

The `$manager->runPending()` method returns an array of strings representing the names of the migrations that were successfully executed during the current batch. The command evaluates this array; if it is empty, it informs the user that there is "Nothing to migrate". If migrations were executed, it loops through the array and outputs a color-coded console message (`\033[32m`) for each migration class that was successfully processed.

# EXAMPLES

**Execute all pending database migrations:**
```bash
php spp.php migrate
```

---

# NAME

migrate:make - Generate a new database migration class

# SYNOPSIS

`php spp.php migrate:make <name> [--name=<name>] [--app=<app_name>]`

# PURPOSE

The `migrate:make` command is used to scaffold a new database migration file within the SPP Framework. It automates the creation of boilerplate PHP classes necessary for defining database schema changes (`up` methods) and their corresponding reversions (`down` methods), ensuring consistency and saving developer time.

# OPTIONS AVAILABLE

*   `<name>` or `--name=<name>` (Required)
    The identifier for the migration. This should briefly describe the schema change (e.g., `create_users_table`). Only alphanumeric characters and underscores are permitted.
*   `--app=<app_name>` (Optional)
    Specifies the sub-application or context for which this migration is being created. If omitted, the framework defaults to the current context returned by `\SPP\Scheduler::getContext()`, or `default` if no context is active.

# UNDER THE HOOD ACTIVITY

When `migrate:make` is executed, the command parses the CLI arguments to extract the desired migration name and the target application context (`appname`). It enforces strict validation on the migration name via a regular expression (`/^[a-zA-Z0-9_]+$/`), rejecting any input containing spaces or special characters to guarantee valid PHP class names.

The command then interfaces with the `\SPP\App` singleton to retrieve the application instance matching the context. It resolves the absolute file path to the application's migration directory by calling `$app->resolvePath('db/migrations', $app->getAppSrcDir())`. If this `db/migrations` directory does not exist, the command recursively creates it with `0755` permissions.

Next, it generates a unique, timestamped class name (e.g., `Migration_20260623_143000_create_users_table`) using `date('Ymd_His')`. It dynamically computes the correct PHP namespace (`App\<Appname>\Migrations`) and populates a heredoc template with the boilerplate code extending `\SPP\Core\Migration`. This stub includes empty `up()` and `down()` methods and a `getVersion()` method returning `'1.0.0'`. Finally, it uses `file_put_contents` to write this PHP string to disk and prints a success message containing the absolute path of the newly created file.

# EXAMPLES

**Create a migration for a new posts table in the default application:**
```bash
php spp.php migrate:make create_posts_table
```

**Create a migration using explicit flags for a specific sub-application:**
```bash
php spp.php migrate:make --name=add_status_to_orders --app=admin
```

---

# NAME
`module:disable` - Disable an SPP module

# SYNOPSIS
`php spp.php module:disable <modulename>`

# PURPOSE
Safely deactivates an installed SPP framework module, preventing its logic, hooks, and routes from being loaded during the application lifecycle, without deleting its data or source files.

# OPTIONS AVAILABLE
- `<modulename>` : The exact registry name of the module to disable.

# UNDER THE HOOD ACTIVITY
The command relies heavily on the core `SPP\Core\ModuleInstaller` class. When invoked with a valid module identifier, it calls the `setModuleStatus()` method, passing the state as `inactive`.
Under the hood, this operation locates the module's registration state (typically stored in a central configuration or database registry) and toggles its active flag. Additionally, deactivating a module inherently triggers a framework-wide cache invalidation and recompilation process, ensuring that the router, dependency injection container, and event dispatchers are purged of the disabled module's bindings. The command wraps this entire procedure in a try-catch block to gracefully handle filesystem permission errors or registry inconsistencies, outputting immediate feedback to standard out.

# EXAMPLES
- `php spp.php module:disable sppdb` - Disables the `sppdb` module.

---

# NAME
`module:enable` - Enable an SPP module

# SYNOPSIS
`php spp.php module:enable <modulename>`

# PURPOSE
Activates an installed, previously disabled SPP module, integrating its functionality, routes, and services back into the live framework ecosystem.

# OPTIONS AVAILABLE
- `<modulename>` : The exact registry name of the module to enable.

# UNDER THE HOOD ACTIVITY
The command utilizes the `SPP\Core\ModuleInstaller` class to mutate the framework's module registry. By invoking `setModuleStatus($moduleName, 'active')`, it updates the configuration tracker to mark the module as bootable.
Activating a module forces the framework to flush its compiled caches. On the next HTTP request or CLI invocation, the SPP kernel will scan the activated module's directory, parse its `module.json` manifest, auto-discover its Service Providers, and register its routes and event listeners. The command handles any underlying exceptions during the cache invalidation or registry write process, guaranteeing that the terminal output accurately reflects the success or failure of the activation request.

# EXAMPLES
- `php spp.php module:enable sppdb` - Enables the `sppdb` module.

---

# NAME
`module:install` - Install or upgrade a specific module or all active modules

# SYNOPSIS
`php spp.php module:install <modulename> [--all]`

# PURPOSE
Initializes and installs a new module, or upgrades an existing one. It triggers the module's installation routines, which may involve copying assets, setting up default configurations, or preparing database schemas.

# OPTIONS AVAILABLE
- `<modulename>` : The target module to install or upgrade. Required unless `--all` is provided.
- `--all` : A flag to recursively trigger the installation/upgrade routine for every currently active module in the system.

# UNDER THE HOOD ACTIVITY
The CLI arguments are parsed to determine whether a batch operation (`--all`) or a targeted installation is requested. 
If the `--all` flag is detected, the command delegates execution to `SPP\Core\ModuleInstaller::installAllActive()`. This method iterates through the framework's registry of active modules and systematically invokes the `install()` routine for each, aggregating the success states and error messages into a structured array which is then printed as a formatted list to the terminal.
If a specific module name is provided, `ModuleInstaller::install($moduleName)` is executed. Under the hood, the installer loads the target module's manifest, resolves its Service Provider, and executes its dedicated `install()` hook. This hook is typically responsible for publishing configuration files, registering scheduled cron tasks, seeding initial database tables, or linking public storage assets. The entire operation is shielded by a try-catch block to prevent a faulty module installation script from causing a fatal crash.

# EXAMPLES
- `php spp.php module:install auth` - Installs or upgrades the `auth` module.
- `php spp.php module:install --all` - Runs the installation routines for all active modules in the application.

---

# NAME
`module:list` - Discovers and tabulates active kernel framework modules

# SYNOPSIS
`php spp.php module:list`

# PURPOSE
Performs a rapid filesystem discovery to list all enterprise modules physically present within the framework's core module directory.

# OPTIONS AVAILABLE
No options required.

# UNDER THE HOOD ACTIVITY
When executed, this command does not query the database or the compiled framework registry. Instead, it performs a raw filesystem traversal. It specifically scans the directory path defined by `SPP_APP_DIR . '/spp/modules/spp'`.
Using PHP's `scandir()` function, it reads the contents of this directory, filtering out the standard `.` and `..` navigational artifacts. It validates that each discovered entity is a valid directory via `is_dir()`. For every valid directory found, it outputs a formatted string to standard output, identifying the directory name as a Module Context. Due to its direct filesystem approach, it serves as a raw diagnostic tool to verify the physical presence of module source code, bypassing potential logical errors in the framework's configuration or caching layers.

# EXAMPLES
- `php spp.php module:list` - Lists all directories recognized as modules.

---

# NAME
`module:setting:list` - List all settings for a given module

# SYNOPSIS
`php spp.php module:setting:list <modname>`

# PURPOSE
Extracts and tabulates the complete configuration schema for a specific module, displaying each setting's key, data type, currently configured value, and default value.

# OPTIONS AVAILABLE
- `<modname>` : The exact registry name of the module whose settings you wish to inspect.

# UNDER THE HOOD ACTIVITY
The command orchestrates an inspection of a module's internal configuration state. It begins by retrieving the module instance via `\SPP\Module::getModule($modname)`. It then invokes `$mod->getSettingsDefinition()`, a method that reads the module's manifest (`module.json` or defined Service Provider) to extract the structural schema of allowable settings, including expected data types and hardcoded default values.
If a schema exists, the command iterates over every defined configuration key. For each key, it performs a live lookup using `\SPP\Module::getConfig($key, $modname)` to retrieve the current, active value (which may be sourced from the database, environment variables, or cache). 
The data is then sanitized—complex or non-scalar values are safely converted to string representations via `json_encode()`—and aggregated into an array. Finally, it passes this normalized data to a CLI table rendering utility (`printTable()`), presenting a clean, formatted matrix of the module's configuration surface directly to the console.

# EXAMPLES
- `php spp.php module:setting:list smtp` - Displays all configurable settings, current values, and defaults for the `smtp` module.

---

# NAME
`module:setting:update` - Update a configuration setting for a specific module

# SYNOPSIS
`php spp.php module:setting:update <modname> <key> <value>`

# PURPOSE
Provides a direct CLI interface to mutate a specific configuration variable within a module's settings registry, bypassing web-based admin interfaces.

# OPTIONS AVAILABLE
- `<modname>` : The name of the module owning the setting.
- `<key>` : The specific configuration key to update.
- `<value>` : The new value to assign to the key.

# UNDER THE HOOD ACTIVITY
This command acts as a strict mutator for module state. After validating that all three required arguments (module name, configuration key, and new value) are present, it directly invokes the core framework API: `\SPP\Module::setConfig($key, $val, $modname)`.
Under the hood, the `setConfig` method performs several critical operations. It first references the module's setting definition schema to cast the incoming string value from the CLI into the appropriate native PHP data type (e.g., boolean, integer, array). It then validates the data. Once validated, the framework persists the new value—typically writing it to a centralized `sys_config` database table or a configuration file—and automatically flushes the relevant application cache so the change takes effect immediately across all runtime contexts. The command catches any schema validation exceptions or database write errors and outputs a color-coded success or failure message to the terminal.

# EXAMPLES
- `php spp.php module:setting:update smtp port 587` - Updates the `port` setting in the `smtp` module to `587`.
- `php spp.php module:setting:update core debug_mode true` - Updates the `debug_mode` key in the `core` module.

---

# NAME
`module:uninstall` - Uninstall a module (drops tracking but retains data tables)

# SYNOPSIS
`php spp.php module:uninstall <modulename>`

# PURPOSE
Safely uninstalls a module by executing its teardown routines and removing it from the active framework registry, while deliberately retaining core database tables to prevent accidental data loss.

# OPTIONS AVAILABLE
- `<modulename>` : The exact registry name of the module to uninstall.

# UNDER THE HOOD ACTIVITY
The command delegates the teardown process to `SPP\Core\ModuleInstaller::uninstall()`. When executed, the installer framework locates the specified module and attempts to invoke its `uninstall()` hook defined within its Service Provider.
This uninstallation hook typically removes scheduled cron jobs, unbinds specific event listeners, clears module-specific temporary files, and drops any volatile cache keys. Crucially, the standard SPP convention dictates that `uninstall` should *not* drop primary database tables containing user data; it only purges configuration tracking and framework integration points. 
Finally, the module's registration flag in the core tracker is removed or set to a fully uninstalled state, followed by a total framework cache purge. The CLI command monitors this process via a try-catch block and provides immediate visual feedback on the terminal regarding the success of the teardown.

# EXAMPLES
- `php spp.php module:uninstall legacy_ui` - Removes the `legacy_ui` module from the application registry.

---

# NAME
`module:update` - Execute the update hook for a specific module

# SYNOPSIS
`php spp.php module:update <modulename> [--from=VERSION] [--to=VERSION]`

# PURPOSE
Triggers the specific, version-aware update logic for a module. This is used to apply schema alterations, data migrations, or configuration upgrades when transitioning a module between specific versions.

# OPTIONS AVAILABLE
- `<modulename>` : The name of the module to update.
- `--from=VERSION` : The version string the module is currently on. Defaults to `unknown`.
- `--to=VERSION` : The version string the module is upgrading to. Defaults to `latest`.

# UNDER THE HOOD ACTIVITY
To perform a precise update, the command first extracts the optional version parameters from the CLI arguments. It then forcefully loads the entire module ecosystem into memory using `\SPP\Module::loadAllModules()`. 
It fetches the specific module object via `getModule()`. If the module is found and active, the command introspects the module's registered `ServiceProvider` instance. It uses PHP's `method_exists()` function to verify if the Service Provider explicitly implements an `update()` method.
If the hook is present, the command executes `$provider->update($fromVersion, $toVersion)`. This method is entirely controlled by the module's author and is typically utilized to execute specialized database `ALTER TABLE` statements, migrate serialized data formats, or patch configuration keys between the specified versions. If the `update()` method throws any exceptions, the command catches them and dumps the error trace to standard output. If the method is absent, it safely skips execution and informs the user.

# EXAMPLES
- `php spp.php module:update auth` - Runs the generic update hook for the `auth` module.
- `php spp.php module:update commerce --from=1.0.2 --to=1.1.0` - Runs the update hook passing specific version boundaries.

---

# NAME
`polyglot:async` - Internal command to execute polyglot calls asynchronously

# SYNOPSIS
`php spp.php polyglot:async [payloadB64]`

# PURPOSE
The `polyglot:async` command is an internal tool designed to trigger cross-language (polyglot) function calls asynchronously. It acts as a fire-and-forget bridge, launching a target module and executing a function in another language environment without waiting for the response.

# OPTIONS AVAILABLE
* `[payloadB64]`
  A base64 encoded JSON string representing the payload. The decoded JSON payload must contain the following keys:
  - `lang` (string): The target language of the module (e.g., 'python', 'node', 'go').
  - `module` (string): The path or identifier of the script/module to be executed.
  - `func` (string): The name of the function to execute within the target module.
  - `args` (array, optional): An array of arguments to be passed to the target function.
  - `daemon` (boolean, optional): Flag indicating whether the module runs as a daemon/worker. Defaults to false.

# UNDER THE HOOD ACTIVITY
When the `polyglot:async` command is invoked, it primarily relies on passing data through the `[payloadB64]` argument. First, the command decodes the base64 string to retrieve the underlying JSON payload. If the decoding fails or the payload is empty, the command silently exits. Upon successful decoding, it extracts the language target (`lang`), the target script or file (`module`), the specific function to invoke (`func`), and any parameters to be supplied (`args`), as well as a boolean flag `daemon`. 

With this data extracted, the command directly invokes the `\SPP\PolyglotBridge::call()` method. The execution happens within a `try-catch` block. Because this command is designed for asynchronous, "fire-and-forget" operations, it ignores the return value from the bridge call. In the event of an exception during execution, it catches the error and logs it using PHP's native `error_log` mechanism rather than bubbling it up to standard output, ensuring that the calling parent process is neither interrupted nor polluted with error outputs.

# EXAMPLES
Execute a Python script asynchronously:
```bash
php spp.php polyglot:async "eyJsYW5nIjoicHl0aG9uIiwibW9kdWxlIjoic2NyaXB0cy9oZWxsby5weSIsImZ1bmMiOiJzYXlfaGVsbG8iLCJhcmdzIjpbIldvcmxkIl0sImRhZW1vbiI6ZmFsc2V9"
```
*(The payload translates to `{"lang":"python","module":"scripts/hello.py","func":"say_hello","args":["World"],"daemon":false}`)*

---

# NAME
`polyglot:list` - Discovers and tabulates all registered polyglot services

# SYNOPSIS
`php spp.php polyglot:list`

# PURPOSE
The `polyglot:list` command automatically scans designated service directories within the SPP framework to discover non-PHP files that act as polyglot microservices or scripts. It then generates a formatted table detailing the detected language and the relative path of each service, making it easy to audit the multi-language integrations within the current application.

# OPTIONS AVAILABLE
This command does not accept any specific options or arguments.

# UNDER THE HOOD ACTIVITY
Upon execution, the command defines three primary search directories to scan for polyglot services: the core `services/` directory, the framework's `src/services/` directory, and the application-specific `services/` folder (`SPP_APP_DIR/services/`). 

It uses PHP's `RecursiveDirectoryIterator` and `RecursiveIteratorIterator` to recursively traverse these directories. For every file encountered, it extracts the file extension and normalizes the path string (replacing backslashes with forward slashes). It checks if the extension matches a known list of supported polyglot languages, which includes `py` (Python), `go`, `js` (Node.js), `rs` (Rust), `rb` (Ruby), `sh` (Shell), `cpp`/`cs` (C++/C#), `java`, and `pl` (Perl). 

Furthermore, to prevent indexing compiled binaries or intermediate build objects, it filters out any paths containing `/obj/` or `/bin/`. If a valid polyglot service file passes these filters, its language (derived from the uppercased file extension) and relative path (stripped of the `SPP_BASE_DIR` prefix) are stored in an array. After completing the filesystem scan, if no services are found, it outputs a default message. Otherwise, it renders the compiled list of services to standard output using a padded, ASCII-art-style table.

# EXAMPLES
List all discovered polyglot services:
```bash
php spp.php polyglot:list
```

---

# NAME
`polyglot:run` - Executes a specific polyglot service directly

# SYNOPSIS
`php spp.php polyglot:run --path=<relative_path_to_service> [args...]`

# PURPOSE
The `polyglot:run` command is utilized to directly invoke a specific polyglot service from the command line, passing down any provided arguments. This is incredibly useful for testing and debugging individual scripts or microservices written in different languages (like Python, Node.js, Go, etc.) without having to trigger them through the main application lifecycle or polyglot bridge interfaces.

# OPTIONS AVAILABLE
* `--path=<relative_path>`
  **Required.** Specifies the relative path (from the base directory) to the polyglot service script that should be executed.
* `[args...]`
  Any additional command-line arguments passed after the path will be securely forwarded directly to the target polyglot script.

# UNDER THE HOOD ACTIVITY
When the command is launched, it iterates through all provided command-line arguments to parse the inputs. It looks for the `--path=` flag to extract the relative file path. It filters out system-level arguments (such as `spp.php`, the command name, and `--app=` flags) and escapes any remaining arguments using `escapeshellarg()` to safely pass them as parameters to the underlying service.

Once the path is determined, the command constructs the full absolute path by prepending `SPP_BASE_DIR` and verifies that the file actually exists on the filesystem. If the file is missing, execution terminates with an error. The command then determines the file extension (e.g., `py`, `js`, `rb`) and uses a hardcoded mapping array to identify the appropriate binary or interpreter (e.g., mapped to `python`, `node`, `ruby`, `bash`, `go run`). If an extension is not registered in the mapping, the process is aborted with an "Unknown interpreter" error.

With the interpreter resolved, the final shell command is composed using `escapeshellcmd()` for the interpreter and script path, appended with the string-joined, escaped service arguments. The constructed command is output to the console for transparency, and then executed synchronously via PHP's `passthru()` function, allowing raw standard output and standard error from the external process to stream directly to the terminal. Finally, it echoes the exit status code returned by the executed process.

# EXAMPLES
Execute a Python script with additional arguments:
```bash
php spp.php polyglot:run --path=services/data_cruncher.py --input=data.csv --verbose
```

Run a Node.js utility:
```bash
php spp.php polyglot:run --path=src/services/mailer.js "recipient@example.com"
```

---

# NAME
`polyglot:status` - Checks the runtime environment for polyglot language binaries

# SYNOPSIS
`php spp.php polyglot:status`

# PURPOSE
The `polyglot:status` command serves as a diagnostic tool. It audits the underlying host server or operating system environment to determine the presence, status, and installed versions of the runtime binaries required to execute polyglot services (e.g., Python, Node.js, Go, Ruby, Rust).

# OPTIONS AVAILABLE
This command does not accept any specific options or arguments.

# UNDER THE HOOD ACTIVITY
When triggered, the command initiates an environment check using a predefined array mapping language names to their respective version check commands (e.g., `'python --version'`, `'node --version'`, `'go version'`). It then prints a formatted ASCII table header to the console.

The core logic iterates over this mapping. For each language, it executes the designated version check command using PHP's `exec()` function, appending `2>&1` to capture both standard output and standard error. It captures the exit code (`$returnVar`) and the command's output array. 

If the command executes successfully (meaning the exit code is exactly `0` and the output is not empty), the script assigns a status of `OK` and concatenates the output array into a single string. To maintain table alignment, it truncates the version string to a maximum of 40 characters using `substr()`. If the command fails (indicated by a non-zero exit code or an empty response), it assigns a status of `MISSING` and sets the version info string to `"Not found in PATH"`. Each evaluated language's results are immediately formatted with `str_pad()` to ensure exact column widths and printed to the terminal, completing the diagnostic table.

# EXAMPLES
Run the environment diagnostic check:
```bash
php spp.php polyglot:status
```

---

# NAME
`polyglot:worker` - Manage Polyglot persistent workers

# SYNOPSIS
`php spp.php polyglot:worker [start|stop|restart|status] <module> [<lang>]`
`php spp.php polyglot:worker async <module> <payloadB64>`

# PURPOSE
The `polyglot:worker` command is responsible for managing background daemon processes (workers) for polyglot services. By keeping polyglot modules running persistently, the application avoids the overhead of booting the interpreter/runtime on every request. This command allows administrators and the framework itself to start, stop, restart, or check the status of these long-running worker processes across varying languages.

# OPTIONS AVAILABLE
* `[action]`
  **Required.** The lifecycle action to perform. Accepted values are `start`, `stop`, `restart`, `status`, or `async`.
* `<module>`
  **Required (except for `status` with no module).** The relative path or identifier of the polyglot module to manage.
* `[<lang>]`
  *Optional.* Explicitly specify the language (e.g., `python`, `node`, `go`, `cs`, `pl`, `java`, `compiler`). If omitted, the command attempts to infer the language from the module's file extension.
* `<payloadB64>`
  *Required when action is `async`.* The base64-encoded JSON payload used for asynchronous execution.

# UNDER THE HOOD ACTIVITY
The command manages the lifecycle of worker processes through PID and port files stored in the framework's shared daemons directory (`var/shared/bridge/daemons`). First, it resolves the absolute path to this directory, creating it if it doesn't exist. It calculates an MD5 hash of the module's realpath to uniquely identify the worker and generate specific `.port`, `.pid`, and `.module` files.

If the action is `async`, it bypasses process management entirely and routes the execution directly to `\SPP\PolyglotBridge::call()` as an asynchronous, fire-and-forget payload processing task.

For the `status` action without a specific module, the command globs the daemons directory for `*.port` files. For each found, it reads the active port and the corresponding module path (from the `.module` file), echoing the state to the terminal.

For the `stop` and `restart` actions, the command checks for the existence of the worker's `.pid` file. If found, it reads the Process ID and issues an OS-specific kill command (`taskkill` on Windows, `kill -9` on Unix). It then deletes the `.pid`, `.port`, and `.module` files. If the action was `stop`, execution terminates.

For the `start` and `restart` actions, the command first prevents duplicate starts by checking the `.pid` file. It determines the language of the module, either from user input or by inference from the extension mapping. It then consults `\SPP\PolyglotBridge::discoverRuntimes()` to locate the correct binary path on the host system. The command dynamically constructs a shell invocation based on the target language. For script languages like Python, Node, and Perl, it wraps the module in a framework-provided dispatch script. For Java, it assembles a classpath. For Go and .NET, it uses native build/run commands. For C++ (`compiler`), it compiles the source to a binary on the fly and points the command to the generated executable. In all cases, the `--daemon` flag and the path to the expected `.port` file are injected.

Finally, the command spawns the process in the background. On Windows, this is achieved using a dynamically generated VBScript (`daemon_runner.vbs`) and a batch file to launch the command silently via `WScript.Shell`. On Unix, it uses `nohup ... & echo $!` to launch the process and capture the PID. The system then waits, polling every 100ms for up to 30 seconds, for the target language dispatcher to bind to an available socket port and write that port number to the `.port` file. If successful, it confirms the binding; otherwise, it warns the user to inspect the generated `.log` file for errors.

# EXAMPLES
Start a Python worker for a machine learning model:
```bash
php spp.php polyglot:worker start modules/ml/predictor.py python
```

Check the status of all running workers:
```bash
php spp.php polyglot:worker status
```

Stop a specific Node.js worker:
```bash
php spp.php polyglot:worker stop services/websockets.js
```

---

# NAME
`profile:report:generate` - Dump a performance profile trace for debugging

# SYNOPSIS
`php spp.php profile:report:generate`

# PURPOSE
The `profile:report:generate` command is a straightforward debugging tool intended to trigger the generation and dumping of a performance profile trace to the filesystem. It is useful when developers need a concrete, snapshot view of profiling telemetry (though its current implementation serves primarily as a stub or placeholder for future enhancements).

# OPTIONS AVAILABLE
This command does not accept any specific options or arguments.

# UNDER THE HOOD ACTIVITY
When the command is executed, it first outputs a status message to standard output indicating that the trace generation is beginning. 

Under the hood, it constructs an absolute file path for the output using the `SPP_BASE_DIR` constant, appending the string `/tmp/profile_` followed by the current UNIX timestamp (generated via PHP's `time()` function) and a `.json` extension. It then uses `file_put_contents()` to write a hardcoded, serialized JSON payload (`{"status":"ok","trace":[]}`) directly into this generated path. Finally, it echoes the path of the generated file back to the console so the user can locate it. 

*(Note: In the current iteration of the framework, this command generates a stubbed empty trace array. The actual integration with the SPPProfile module for live trace dumping appears to be slated for future development or is handled externally.)*

# EXAMPLES
Generate a performance profile trace:
```bash
php spp.php profile:report:generate
```
Expected output:
```
Generating performance profile trace...
Report generated at: /path/to/project/tmp/profile_1690000000.json
```

---

# NAME
`profile:status` - Check if the performance profiler is running/enabled

# SYNOPSIS
`php spp.php profile:status`

# PURPOSE
The `profile:status` command is used to quickly determine the health and operational status of the `SPPProfile` module within the framework. It checks whether the application's performance profiler is currently active and monitoring execution traces.

# OPTIONS AVAILABLE
This command does not accept any specific options or arguments.

# UNDER THE HOOD ACTIVITY
The command performs a lightweight runtime check using PHP's `class_exists()` function. It specifically looks for the `\SPPMod\SPPProfile\SPPProfile` class definition in the current execution environment. 

If the class is found in memory, the command infers that the profiler module has been installed, loaded, and initialized properly by the framework. It then prints out a confirmation that the module is `ACTIVE` and is currently "Monitoring performance traces". Conversely, if the class is not found, it outputs that the module is `NOT ACTIVE`, indicating that profiling is either uninstalled or disabled in the environment configurations.

# EXAMPLES
Check the status of the performance profiler:
```bash
php spp.php profile:status
```

---

# NAME
`queue:list` - List all jobs currently in the queue

# SYNOPSIS
`php spp.php queue:list [--app=<app_name>]`

# PURPOSE
The `queue:list` command queries the background job queue (stored in the database) and outputs a tabular list of jobs that are currently pending execution. It provides developers and administrators with immediate visibility into the backlog, showing job IDs, scheduling times, and small snippets of the job payloads.

# OPTIONS AVAILABLE
* `--app=<app_name>`
  *Optional.* Specifies the application context to connect to. Defaults to `default`. If the system utilizes multiple databases or contextual queues based on the application name, this flag ensures the query targets the correct `spp_jobs` table.

# UNDER THE HOOD ACTIVITY
When the command is run, it first parses the command-line arguments to extract any provided `--app=` flag, defaulting to `'default'`. It then delegates the core execution to the `\SPP\Scheduler::withContext()` method, wrapping the logic inside a closure that runs under the targeted application context.

Inside the context, the command attempts to instantiate the database connection via `\SPPMod\SPPDB\SPPDB()`. Because database connections can fail or the module might not be present, this instantiation is wrapped in output buffering (`ob_start()`) and a `try-catch` block. If the connection fails, the buffers are cleaned, and a graceful error message is displayed indicating the queue is empty or unavailable.

Once the database connection is secured, the command executes a raw SQL query: `SELECT * FROM spp_jobs ORDER BY available_at ASC LIMIT 50`. If the queue is empty, the process returns immediately with a notification. Otherwise, it prints a formatted ASCII table header.

For each retrieved job row, the command extracts the `id`, `available_at`, and `created_at` fields. It grabs the serialized JSON string from the `payload` column and truncates it to a maximum of 30 characters (appending `...` if it exceeds this length) to form a safe, readable snippet. These fields are spaced using `str_pad` and printed to the terminal. Finally, it executes a secondary `COUNT(*)` query to determine the total number of jobs residing in the `spp_jobs` table and prints a summary footer, highlighting whether the console is showing a limited view (50 out of X jobs) or the entirety of the queue.

# EXAMPLES
List pending jobs for the default application:
```bash
php spp.php queue:list
```

List pending jobs for a specific application:
```bash
php spp.php queue:list --app=admin_panel
```

---

# NAME
`queue:work` - Starts a worker loop to process background jobs from the queue

# SYNOPSIS
`php spp.php queue:work`

# PURPOSE
The `queue:work` command instantiates a long-running, blocking daemon process that continuously polls the application's queue for pending background jobs. As jobs become available, the worker executes their logic and safely removes them from the queue upon successful completion. It is a critical component for processing delayed tasks such as sending emails, batch data crunching, or asynchronous API calls.

# OPTIONS AVAILABLE
This command does not accept any specific options or arguments.

# UNDER THE HOOD ACTIVITY
When executed, the command enters an infinite `while (true)` loop, transforming the PHP script into a persistent daemon worker. At the start of each iteration, it calls `\SPP\Core\Queue::pop()`. This static method interacts with the underlying storage (usually the database `spp_jobs` table) to retrieve the next available, unprocessed job whose `available_at` timestamp is less than or equal to the current time.

If `Queue::pop()` returns a valid job array, the command extracts the `id` string and the instantiated `job` object (which is typically unserialized from the payload by the Queue core). It echoes a timestamped log to standard output indicating that the specific Job ID and job class type are being processed.

The actual execution occurs inside a `try-catch (\Throwable $e)` block to prevent a single faulty job from crashing the entire worker daemon. Inside the `try`, the command invokes the `$job->handle()` method, triggering the business logic defined by the developer. If `handle()` executes without throwing an exception, the command immediately calls `\SPP\Core\Queue::complete($id)` to permanently delete or mark the job as finished in the storage layer, preventing duplicate processing.

If an exception is thrown during `handle()`, the `catch` block traps it and logs a timestamped failure message containing the error details to the terminal. Currently, failed jobs remain in the system based on the core Queue implementation (often left in the table or moved to a failed jobs table, though this loop simply logs and moves on).

If `Queue::pop()` returns `null` (meaning the queue is currently empty), the loop executes a `sleep(2)` command. This 2-second pause is crucial for resource management, preventing the `while(true)` loop from consuming 100% CPU usage while idly polling the empty database.

# EXAMPLES
Start the queue worker daemon:
```bash
php spp.php queue:work
```

---

## `schedule:run`

**Purpose**: Run all scheduled cron tasks declared by active modules

### Synopsis
```bash
php spp.php schedule:run [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPP\Cron\Scheduler.


---

# NAME

`serve`

# SYNOPSIS

`php spp.php serve [--port=<port>]`

# PURPOSE

Bootstraps the Universal Execution pillar by spinning up local development servers for Native, Blade, or Admin apps.

# OPTIONS AVAILABLE

- `--port=<port>` : Specify the port on which the development server should listen. Defaults to `8000`.

# UNDER THE HOOD ACTIVITY

Upon execution, the command parses the CLI arguments for the `--port=` parameter, extracting the integer port value (defaulting to 8000). It retrieves the current application context via `\SPP\Scheduler::getContext()`. It then outputs a colored console header detailing the active context, the local URL, and the administrative URL.

The process then sequentially spawns two distinct PHP server instances. First, it calculates a Hot Module Replacement (HMR) port by adding 1 to the target port. It constructs a background execution command (`start /b php -S localhost:{hmrPort} hmr.php`) and fires it asynchronously via the `exec()` function. 

Immediately following, the command constructs the foreground primary application server command utilizing PHP's built-in web server (`php -S localhost:{port} -t {SPP_APP_DIR}`). It sanitizes the document root path with `escapeshellarg()` and utilizes `passthru()` to hijack the console's standard output, effectively keeping the main process alive and bound to the server stream until the user terminates it with Ctrl+C.

# EXAMPLES

Start the server on default port 8000:
```bash
php spp.php serve
```

Start the server on port 8080:
```bash
php spp.php serve --port=8080
```

---

## `service:crud`

**Purpose**: Manage SPP services (list, create, edit, delete)

### Synopsis
```bash
php spp.php service:crud [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `session:clean`

**Purpose**: Clean up expired sessions

### Synopsis
```bash
php spp.php session:clean [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `session:destroy-all`

**Purpose**: Invalidate all active sessions across the application

### Synopsis
```bash
php spp.php session:destroy-all [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `site:install`

**Purpose**: Initialize the database and load default configurations for a specific profile.

### Synopsis
```bash
php spp.php site:install [OPTIONS]
```

### Options Available
- `--profile=` : Expects a value. Extracted via static analysis from SiteInstallCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `storage:clean`

**Purpose**: Clean up temporary files in storage

### Synopsis
```bash
php spp.php storage:clean [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from StorageCleanCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Bootstraps a full application execution context via Scheduler.


---

## `storage:link`

**Purpose**: Create symbolic links for public storage

### Synopsis
```bash
php spp.php storage:link [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from StorageLinkCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Bootstraps a full application execution context via Scheduler.


---

## `storage:sync`

**Purpose**: Sync local storage with external disks (stub)

### Synopsis
```bash
php spp.php storage:sync [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from StorageSyncCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

# sys:debug

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

---

## `sys:seed`

**Purpose**: Run all database seeders for an application

### Synopsis
```bash
php spp.php sys:seed [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPPDB.


---

# NAME
**sys:test:auto** - Runs Automated Evolutionary Testing (Parikshak) for the current application.

# SYNOPSIS
`php spp.php sys:test:auto [appname]`

# PURPOSE
Executes the Parikshak Automated Evaluation suite, verifying system integrity, checking entity invariants, and running all associated unit tests for a designated application context.

# OPTIONS AVAILABLE
- `[appname]` : **Optional.** The name of the application context to evaluate. Defaults to the current active context retrieved via `\SPP\Scheduler::getContext()`, or `default`.

# UNDER THE HOOD ACTIVITY
The command first checks if the `parikshak` module is enabled via `\SPP\Module::getConfig('active', 'parikshak')`. If inactive, it aborts. It isolates the testing environment by dynamically overwriting the database configuration (`sppdb` module) to use an in-memory SQLite database (`:memory:`), and injects a fresh `\SPPMod\SPPDB\SPPDB` instance. It then instantiates `\SPPMod\Parikshak\Parikshak` and calls `runSuite($appname)`. The suite runs entity evaluations and unit tests, and returns an array of results. Finally, the CLI formats and renders a comprehensive console report, highlighting passed/failed entity rules and unit test outcomes with ANSI color codes.

# EXAMPLES
Run tests for the default context:
`php spp.php sys:test:auto`

Run tests for a specific app context named "api":
`php spp.php sys:test:auto api`

---

## `sys:upgrade`

**Purpose**: Synchronize the database schema incrementally from all active module definitions (db.yml)

### Synopsis
```bash
php spp.php sys:upgrade [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPPDB.


---

# NAME
**test** - Run Parikshak Unit and Feature Tests

# SYNOPSIS
`php spp.php test [--coverage]`

# PURPOSE
A dedicated testing command to execute all Unit and Feature tests registered in the Parikshak module for the current active SPP context. 

# OPTIONS AVAILABLE
- `--coverage` : **Optional.** If provided, the test runner will collect and calculate code coverage metrics during the execution.

# UNDER THE HOOD ACTIVITY
The command resolves the current context via `\SPP\Scheduler::getContext()`. It enforces strict database isolation for tests by mutating the system configuration at runtime to use a transient, in-memory SQLite database (`:memory:`). The `\SPPMod\SPPDB\SPPDB` service provider is reset to use this environment. It then instantiates the `\SPPMod\Parikshak\SPPTestRunner` and calls `run($context, $withCoverage)`. The runner executes all tests and aggregates a summary. The CLI iterates over the returned test list, printing the status (pass/fail) with ANSI styling. If any tests fail, the script terminates with a non-zero exit code (`exit(1)`), which is useful for CI/CD pipelines.

# EXAMPLES
Run all tests:
`php spp.php test`

Run tests and generate coverage:
`php spp.php test --coverage`

---

## `test:blueprint`

**Purpose**: Generate a structural blueprint for an entity

### Synopsis
```bash
php spp.php test:blueprint [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:blueprint <EntityClass>

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from TestBlueprintCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: parikshak.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\Parikshak\Parikshak.


---

## `test:module`

**Purpose**: Run PHPUnit tests for an isolated module

### Synopsis
```bash
php spp.php test:module [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:module <modulename>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.


---

## `test:monkey`

**Purpose**: Runs chaos monkey / fuzzing scenarios for an entity

### Synopsis
```bash
php spp.php test:monkey [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:monkey <EntityClass>

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from TestMonkeyCommand.php.
- `--entities` : Boolean flag or option. Extracted via static analysis from TestMonkeyCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: parikshak.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\Parikshak\Parikshak.


---

## `test:run`

**Purpose**: Runs Parikshak evaluation for an entity or the whole suite

### Synopsis
```bash
php spp.php test:run [OPTIONS]
```

### Options Available
- `--coverage` : Boolean flag or option. Extracted via static analysis from TestRunCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: parikshak.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\Parikshak\Parikshak.


---

## `theme:activate`

**Purpose**: Switch the active theme adapter (native/wp/joomla) and optionally set the theme name

### Synopsis
```bash
php spp.php theme:activate [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `tinker`

**Purpose**: Interact with your application in a REPL shell.

### Synopsis
```bash
php spp.php tinker [OPTIONS]
```

### Options Available
- `--force` : Boolean flag or option. Extracted via static analysis from TinkerCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `ux:debug`

**Purpose**: Toggle SPP-UX verbose logging (on|off)

### Synopsis
```bash
php spp.php ux:debug [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `verify:sovereignty`

**Purpose**: Validates complete stack self-containment/zero external links

### Synopsis
```bash
php spp.php verify:sovereignty [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `view:cache`

**Purpose**: Pre-compiles all AST views into PHP for optimal performance

### Synopsis
```bash
php spp.php view:cache [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `view:page:add`

**Purpose**: Add a new page route to an app

### Synopsis
```bash
php spp.php view:page:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:page:add --name=<route> --url=<target> [--app=default] [--source=yaml|db]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php.
- `--name=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php.
- `--url=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php.
- `--source=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: page.


---

## `view:page:list`

**Purpose**: List all registered pages/routes for an app

### Synopsis
```bash
php spp.php view:page:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewPageListCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `view:page:remove`

**Purpose**: Remove a page route from an app

### Synopsis
```bash
php spp.php view:page:remove [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:page:remove --name=<route> [--app=default] [--source=yaml|db]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewPageRemoveCommand.php.
- `--name=` : Expects a value. Extracted via static analysis from ViewPageRemoveCommand.php.
- `--source=` : Expects a value. Extracted via static analysis from ViewPageRemoveCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `view:service:add`

**Purpose**: Register a new AJAX service endpoint

### Synopsis
```bash
php spp.php view:service:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:add --name=<service> --script=<path> [--method=POST] [--app=default] [--source=yaml|db]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.
- `--script=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.
- `--method=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.
- `--source=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: AJAX.


---

## `view:service:list`

**Purpose**: List all registered AJAX services for an app

### Synopsis
```bash
php spp.php view:service:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceListCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `view:service:remove`

**Purpose**: Remove an AJAX service endpoint from an app

### Synopsis
```bash
php spp.php view:service:remove [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:remove --name=<service> [--app=default] [--source=yaml|db]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceRemoveCommand.php.
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceRemoveCommand.php.
- `--source=` : Expects a value. Extracted via static analysis from ViewServiceRemoveCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `view:service:test`

**Purpose**: Test an AJAX service endpoint from the CLI

### Synopsis
```bash
php spp.php view:service:test [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:test --name=<service> [--app=default] [--payload=
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php.
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php.
- `--payload=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

# xdb:describe

## NAME
`xdb:describe` - Describe the schema of an XDB table.

## SYNOPSIS
`php spp xdb:describe <table_name> [--db=<dbname>]`

## PURPOSE
Provides a clear view of the schema configuration for a specific table within the SPPXDB XML database. Useful for debugging and understanding the structure of stored XML entities.

## OPTIONS AVAILABLE
* `<table_name>`: The name of the table to describe. Required.
* `--db=<dbname>`: The target database name. Defaults to `default`.

## UNDER THE HOOD ACTIVITY
The `xdb:describe` command iterates through the provided arguments, extracting the `--db=` option to determine the target database context. The first unflagged argument that isn't the script or command name is treated as the table name. If no table name is provided, the command halts and displays the usage instructions.

It then requires the `SPP_XDB` core module class from `modules/spp/sppxdb/class.sppxdb.php`. An instance of `\SPPMod\SPPXDB\SPP_XDB` is instantiated, passing the extracted database name (or 'default') to its constructor. It queries the schema by executing the `DESCRIBE <table_name>` SQL command through the `$xdb->querySQL()` method. 

Once the schema definition is fetched, it attempts to display the results cleanly. If a global helper function `printTable` exists within the CLI context, it utilizes it to render the output in a formatted table layout using the array keys of the first row as headers. Otherwise, it falls back to displaying the raw array using `print_r`.

## EXAMPLES
* `php spp xdb:describe users`
* `php spp xdb:describe orders --db=ecommerce`

---

# xdb:list-dbs

## NAME
`xdb:list-dbs` - List all available XDB databases.

## SYNOPSIS
`php spp xdb:list-dbs`

## PURPOSE
Outputs a list of all databases managed by the SPPXDB system.

## OPTIONS AVAILABLE
This command currently accepts no arguments or options.

## UNDER THE HOOD ACTIVITY
When executed, `xdb:list-dbs` loads the core `SPP_XDB` class by explicitly requiring `modules/spp/sppxdb/class.sppxdb.php`. It initializes a new instance of the `SPP_XDB` engine without a specific database parameter. It subsequently calls the `$xdb->querySQL("SHOW DATABASES")` method to instruct the engine to scan for existing database containers. 

The returned result set is then iterated over, extracting the `'Database'` key from each row to print a bulleted list to standard output. If the query returns an empty set, it informs the user that no databases were found. Any exceptions triggered during the loading or querying phase are caught and printed as error messages.

## EXAMPLES
* `php spp xdb:list-dbs`

---

# xdb:list-tables

## NAME
`xdb:list-tables` - List all tables in an XDB database.

## SYNOPSIS
`php spp xdb:list-tables [--db=<dbname>]`

## PURPOSE
Displays all the available tables within a specified SPPXDB database context.

## OPTIONS AVAILABLE
* `--db=<dbname>`: Specifies which database to inspect. If omitted, defaults to `default`.

## UNDER THE HOOD ACTIVITY
The `xdb:list-tables` command begins by parsing arguments to extract the `--db=` parameter; if it is not provided, it falls back to targeting the `'default'` database. It then loads the `class.sppxdb.php` file from the `modules/spp/sppxdb` directory.

It instantiates the `SPP_XDB` class, passing the targeted database name into its constructor. The tool executes the SQL query `SHOW TABLES` via the `$xdb->querySQL()` method. 

Once the result set is retrieved, it checks if it is empty. If tables are found, it iterates over each row of the result. Since `SHOW TABLES` typically returns rows with a single value containing the table name, it uses the `current()` PHP function to extract and print each table name in a formatted list. Error handling catches and prints any exceptions that happen during the execution.

## EXAMPLES
* `php spp xdb:list-tables`
* `php spp xdb:list-tables --db=ecommerce`

---

# xdb:query

## NAME
`xdb:query` - Execute a SQL or XPath query on the XML database.

## SYNOPSIS
`php spp xdb:query "<query>" [--type=<sql|xpath>]`

## PURPOSE
Executes queries directly against the SPPXDB system using the command line. This allows developers to interact with the underlying XML-based data structures without needing a specialized client, supporting both traditional SQL-like queries and raw XPath expressions.

## OPTIONS AVAILABLE
* `"<query>"`: The actual SQL or XPath string to execute. It should be enclosed in quotes to prevent shell parsing issues. This is the first non-option argument.
* `--type=<sql|xpath>`: Specifies the query engine to use. Defaults to `sql`.
  - `sql`: Uses the SQL parser to interpret the query.
  - `xpath`: Passes the query directly to the XPath engine.

## UNDER THE HOOD ACTIVITY
When `xdb:query` is invoked, it parses the command-line arguments to separate the query string from the options. It ignores the script name (`spp.php` or `spp/spp.php`) and the command name itself (`xdb:query`). It extracts the `--type` flag if present, defaulting to `sql` otherwise.

The command then dynamically attempts to locate and load the `SPP_XDB` core class from `modules/spp/sppxdb/class.sppxdb.php`. If the file is missing, it throws an exception.

Once the class is loaded, it instantiates `\SPPMod\SPPXDB\SPP_XDB`. Depending on the query type specified, it calls either `$xdb->queryX($query)` for XPath expressions or `$xdb->querySQL($query)` for SQL statements. The XPath execution assumes the user is either targeting a global scope or implicitly relying on a default connection state. Finally, the results are captured; if an array of records is returned, it prints the count and the raw output via `print_r`. If a non-array response (like a boolean for an insert/update) is returned, it outputs the result using `var_export`.

## EXAMPLES
* `php spp xdb:query "SELECT * FROM users"`
* `php spp xdb:query "//user[age>18]" --type=xpath`

---

# xdb:shell

## NAME
`xdb:shell` - Launch the interactive SPPXDB shell.

## SYNOPSIS
`php spp xdb:shell`

## PURPOSE
Initiates an interactive read-eval-print loop (REPL) shell environment for interacting with the SPPXDB database, enabling continuous query execution without repeatedly invoking the `spp` CLI.

## OPTIONS AVAILABLE
This command accepts no parameters. All interactions happen within the REPL after launch.

## UNDER THE HOOD ACTIVITY
The `xdb:shell` command functions as a proxy to launch a dedicated interactive script. Upon execution, it dynamically computes the path to `xdb-shell.php` located in `modules/spp/sppxdb/xdb-shell.php`. 

It first verifies the existence of this file; if it is missing, it aborts with an error message. If the file is present, the command includes the shell script directly into the current process using the `include()` statement. This hands over control of the standard input/output streams to the `xdb-shell.php` script, which contains its own infinite loop for parsing user input, executing SQL/XPath queries against the `SPP_XDB` engine, and printing the formatted results back to the console until the user exits.

## EXAMPLES
* `php spp xdb:shell`

---

