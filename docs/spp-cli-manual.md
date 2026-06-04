# SPP CLI Manual

Detailed reference for all `spp.php` commands.

## Table of Contents
- [`ai:prompt`](#aiprompt)
- [`ai:providers`](#aiproviders)
- [`api:key:generate`](#apikeygenerate)
- [`api:key:revoke`](#apikeyrevoke)
- [`api:route:list`](#apiroutelist)
- [`app:config`](#appconfig)
- [`app:create`](#appcreate)
- [`app:default`](#appdefault)
- [`app:list`](#applist)
- [`app:set-base`](#appsetbase)
- [`audit:lineage`](#auditlineage)
- [`auth:group:assign`](#authgroupassign)
- [`auth:group:create`](#authgroupcreate)
- [`auth:group:edit`](#authgroupedit)
- [`auth:group:list`](#authgrouplist)
- [`auth:group:member:add`](#authgroupmemberadd)
- [`auth:group:member:list`](#authgroupmemberlist)
- [`auth:right:create`](#authrightcreate)
- [`auth:right:delete`](#authrightdelete)
- [`auth:right:list`](#authrightlist)
- [`auth:role:assign`](#authroleassign)
- [`auth:role:create`](#authrolecreate)
- [`auth:role:delete`](#authroledelete)
- [`auth:role:edit`](#authroleedit)
- [`auth:role:list`](#authrolelist)
- [`auth:user:assign`](#authuserassign)
- [`auth:user:create`](#authusercreate)
- [`auth:user:delete`](#authuserdelete)
- [`auth:user:edit`](#authuseredit)
- [`auth:user:list`](#authuserlist)
- [`auth:user:password`](#authuserpassword)
- [`blade:clear`](#bladeclear)
- [`blade:view`](#bladeview)
- [`build:edge`](#buildedge)
- [`cache:clear`](#cacheclear)
- [`cache:purge`](#cachepurge)
- [`cache:stats`](#cachestats)
- [`cache:warmup`](#cachewarmup)
- [`cli:app:default`](#cliappdefault)
- [`config`](#config)
- [`config:export`](#configexport)
- [`config:import`](#configimport)
- [`config:sync`](#configsync)
- [`create:app`](#createapp)
- [`cron:flush`](#cronflush)
- [`cron:list`](#cronlist)
- [`cron:run`](#cronrun)
- [`db:verify`](#dbverify)
- [`dbsettings:export`](#dbsettingsexport)
- [`dbsettings:import`](#dbsettingsimport)
- [`delete:app`](#deleteapp)
- [`di:list`](#dilist)
- [`diff:apply`](#diffapply)
- [`diff:compare`](#diffcompare)
- [`diff:history`](#diffhistory)
- [`diff:rollback`](#diffrollback)
- [`drishyam:clear`](#drishyamclear)
- [`drishyam:compile`](#drishyamcompile)
- [`drishyam:theme:check`](#drishyamthemecheck)
- [`ent:delete`](#entdelete)
- [`ent:edit`](#entedit)
- [`ent:list`](#entlist)
- [`ent:manage`](#entmanage)
- [`ent:query`](#entquery)
- [`ent:show`](#entshow)
- [`env:backup`](#envbackup)
- [`env:get`](#envget)
- [`env:list`](#envlist)
- [`env:set`](#envset)
- [`env:status`](#envstatus)
- [`env:token:rotate`](#envtokenrotate)
- [`event:fire`](#eventfire)
- [`event:list-listeners`](#eventlistlisteners)
- [`ext:disable`](#extdisable)
- [`ext:enable`](#extenable)
- [`ext:install`](#extinstall)
- [`ext:list`](#extlist)
- [`forge`](#forge)
- [`frontend:debug`](#frontenddebug)
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
- [`make:blade-project`](#makebladeproject)
- [`make:blade-scaffold`](#makebladescaffold)
- [`make:command`](#makecommand)
- [`make:controller`](#makecontroller)
- [`make:drupal-bridge`](#makedrupalbridge)
- [`make:entity`](#makeentity)
- [`make:form`](#makeform)
- [`make:go-service`](#makegoservice)
- [`make:middleware`](#makemiddleware)
- [`make:model`](#makemodel)
- [`make:module`](#makemodule)
- [`make:python-service`](#makepythonservice)
- [`make:react-component`](#makereactcomponent)
- [`make:scaffold`](#makescaffold)
- [`make:service`](#makeservice)
- [`make:ux-component`](#makeuxcomponent)
- [`make:view`](#makeview)
- [`make:vue-component`](#makevuecomponent)
- [`man`](#man)
- [`man:generate`](#mangenerate)
- [`manifest:export`](#manifestexport)
- [`marketing:campaign:dispatch`](#marketingcampaigndispatch)
- [`marketing:campaign:sync`](#marketingcampaignsync)
- [`middleware:list`](#middlewarelist)
- [`migrate:deploy`](#migratedeploy)
- [`module:list`](#modulelist)
- [`polyglot:list`](#polyglotlist)
- [`polyglot:run`](#polyglotrun)
- [`polyglot:status`](#polyglotstatus)
- [`profile:report:generate`](#profilereportgenerate)
- [`profile:status`](#profilestatus)
- [`pwa:build`](#pwabuild)
- [`pwa:manifest`](#pwamanifest)
- [`pwa:service-worker-gen`](#pwaserviceworkergen)
- [`queue:list`](#queuelist)
- [`queue:work`](#queuework)
- [`serve`](#serve)
- [`session:clean`](#sessionclean)
- [`session:destroy-all`](#sessiondestroyall)
- [`site:install`](#siteinstall)
- [`storage:clean`](#storageclean)
- [`storage:link`](#storagelink)
- [`storage:sync`](#storagesync)
- [`sys:bridge:info`](#sysbridgeinfo)
- [`sys:bridge:setup`](#sysbridgesetup)
- [`sys:debug`](#sysdebug)
- [`sys:info`](#sysinfo)
- [`sys:test:auto`](#systestauto)
- [`sys:update`](#sysupdate)
- [`test:blueprint`](#testblueprint)
- [`test:monkey`](#testmonkey)
- [`test:run`](#testrun)
- [`theme:activate`](#themeactivate)
- [`ui:build`](#uibuild)
- [`ui:comp:php`](#uicompphp)
- [`ui:serv`](#uiserv)
- [`ui:store`](#uistore)
- [`ui:view`](#uiview)
- [`ui:watch`](#uiwatch)
- [`userprofile:export`](#userprofileexport)
- [`userprofile:schema:update`](#userprofileschemaupdate)
- [`ux:debug`](#uxdebug)
- [`verify:sovereignty`](#verifysovereignty)
- [`view:page:add`](#viewpageadd)
- [`view:page:list`](#viewpagelist)
- [`view:page:remove`](#viewpageremove)
- [`view:service:add`](#viewserviceadd)
- [`view:service:list`](#viewservicelist)
- [`view:service:remove`](#viewserviceremove)
- [`view:service:test`](#viewservicetest)
- [`wizard:list`](#wizardlist)
- [`wizard:validate`](#wizardvalidate)
- [`xdb:describe`](#xdbdescribe)
- [`xdb:list-dbs`](#xdblistdbs)
- [`xdb:list-tables`](#xdblisttables)
- [`xdb:query`](#xdbquery)
- [`xdb:shell`](#xdbshell)

---

## `ai:prompt`

**Description**: Send a prompt to the AI provider

### Synopsis
```bash
php spp.php ai:prompt [OPTIONS]
```

### Usage
```text
Usage: php spp.php ai:prompt \
```

---

## `ai:providers`

**Description**: List all registered AI providers

### Synopsis
```bash
php spp.php ai:providers [OPTIONS]
```

---

## `api:key:generate`

**Description**: Generate a new API access token

### Synopsis
```bash
php spp.php api:key:generate [OPTIONS]
```

---

## `api:key:revoke`

**Description**: Revoke an existing API token

### Synopsis
```bash
php spp.php api:key:revoke [OPTIONS]
```

### Usage
```text
Usage: php spp.php api:key:revoke --token=<token>
```

---

## `api:route:list`

**Description**: Tabulate all exposed REST API routes

### Synopsis
```bash
php spp.php api:route:list [OPTIONS]
```

---

## `app:config`

**Description**: Configure application settings (e.g., base_url, table_prefix)

### Synopsis
```bash
php spp.php app:config [OPTIONS]
```

### Usage
```text
Usage: php spp.php app:config <app_name> [--base_url=...] [--table_prefix=...]
```

---

## `app:create`

**Description**: Legacy port of app:create

### Synopsis
```bash
php spp.php app:create [OPTIONS]
```

---

## `app:default`

**Description**: Set or view the default global CLI application context

### Synopsis
```bash
php spp.php app:default [OPTIONS]
```

---

## `app:list`

**Description**: List all registered SPP applications

### Synopsis
```bash
php spp.php app:list [OPTIONS]
```

---

## `app:set-base`

**Description**: Set an application as the primary/base application

### Synopsis
```bash
php spp.php app:set-base [OPTIONS]
```

### Usage
```text
Usage: php spp.php app:set-base <app_name>
```

---

## `audit:lineage`

**Description**: Traverses and verifies cryptographic Merkle-DAG trace logs

### Synopsis
```bash
php spp.php audit:lineage [OPTIONS]
```

---

## `auth:group:assign`

**Description**: Legacy port of auth:group:assign

### Synopsis
```bash
php spp.php auth:group:assign [OPTIONS]
```

---

## `auth:group:create`

**Description**: Legacy port of auth:group:create

### Synopsis
```bash
php spp.php auth:group:create [OPTIONS]
```

---

## `auth:group:edit`

**Description**: Legacy port of auth:group:edit

### Synopsis
```bash
php spp.php auth:group:edit [OPTIONS]
```

---

## `auth:group:list`

**Description**: Legacy port of auth:group:list

### Synopsis
```bash
php spp.php auth:group:list [OPTIONS]
```

---

## `auth:group:member:add`

**Description**: Legacy port of auth:group:member:add

### Synopsis
```bash
php spp.php auth:group:member:add [OPTIONS]
```

---

## `auth:group:member:list`

**Description**: Legacy port of auth:group:member:list

### Synopsis
```bash
php spp.php auth:group:member:list [OPTIONS]
```

---

## `auth:right:create`

**Description**: Define a new granular right/permission

### Synopsis
```bash
php spp.php auth:right:create [OPTIONS]
```

### Usage
```text
Usage: php spp.php auth:right:create --key=<right_key>
```

---

## `auth:right:delete`

**Description**: Remove a right from the system

### Synopsis
```bash
php spp.php auth:right:delete [OPTIONS]
```

### Usage
```text
Usage: php spp.php auth:right:delete --key=<right_key>
```

---

## `auth:right:list`

**Description**: Legacy port of auth:right:list

### Synopsis
```bash
php spp.php auth:right:list [OPTIONS]
```

---

## `auth:role:assign`

**Description**: Legacy port of auth:role:assign

### Synopsis
```bash
php spp.php auth:role:assign [OPTIONS]
```

---

## `auth:role:create`

**Description**: Legacy port of auth:role:create

### Synopsis
```bash
php spp.php auth:role:create [OPTIONS]
```

---

## `auth:role:delete`

**Description**: Delete a role from the system

### Synopsis
```bash
php spp.php auth:role:delete [OPTIONS]
```

### Usage
```text
Usage: php spp.php auth:role:delete --id=<role_id>
```

---

## `auth:role:edit`

**Description**: Legacy port of auth:role:edit

### Synopsis
```bash
php spp.php auth:role:edit [OPTIONS]
```

---

## `auth:role:list`

**Description**: Legacy port of auth:role:list

### Synopsis
```bash
php spp.php auth:role:list [OPTIONS]
```

---

## `auth:user:assign`

**Description**: Legacy port of auth:user:assign

### Synopsis
```bash
php spp.php auth:user:assign [OPTIONS]
```

---

## `auth:user:create`

**Description**: Legacy port of auth:user:create

### Synopsis
```bash
php spp.php auth:user:create [OPTIONS]
```

---

## `auth:user:delete`

**Description**: Delete a user account safely

### Synopsis
```bash
php spp.php auth:user:delete [OPTIONS]
```

### Usage
```text
Usage: php spp.php auth:user:delete --id=<user_id>
```

---

## `auth:user:edit`

**Description**: Legacy port of auth:user:edit

### Synopsis
```bash
php spp.php auth:user:edit [OPTIONS]
```

---

## `auth:user:list`

**Description**: Legacy port of auth:user:list

### Synopsis
```bash
php spp.php auth:user:list [OPTIONS]
```

---

## `auth:user:password`

**Description**: Update the password of an existing user

### Synopsis
```bash
php spp.php auth:user:password [OPTIONS]
```

### Usage
```text
Usage: php spp.php auth:user:password --id=<user_id> --pass=<new_password>
```

---

## `blade:clear`

**Description**: Clear the compiled Blade view cache

### Synopsis
```bash
php spp.php blade:clear [OPTIONS]
```

---

## `blade:view`

**Description**: Manage Blade views (list, create, delete)

### Synopsis
```bash
php spp.php blade:view [OPTIONS]
```

---

## `build:edge`

**Description**: Legacy port of build:edge

### Synopsis
```bash
php spp.php build:edge [OPTIONS]
```

---

## `cache:clear`

**Description**: Clear the application file/redis cache

### Synopsis
```bash
php spp.php cache:clear [OPTIONS]
```

---

## `cache:purge`

**Description**: Purge cache tags or URLs from the reverse proxy (Varnish/CDN).

### Synopsis
```bash
php spp.php cache:purge [OPTIONS]
```

---

## `cache:stats`

**Description**: Display cache driver statistics

### Synopsis
```bash
php spp.php cache:stats [OPTIONS]
```

---

## `cache:warmup`

**Description**: Warm up common application caches

### Synopsis
```bash
php spp.php cache:warmup [OPTIONS]
```

---

## `cli:app:default`

**Description**: Legacy port of cli:app:default

### Synopsis
```bash
php spp.php cli:app:default [OPTIONS]
```

---

## `config`

**Description**: Manage framework and application configuration

### Synopsis
```bash
php spp.php config [OPTIONS]
```

### Usage
```text
Usage: spp config [get|set|cache|clear] [key] [value]
```

---

## `config:export`

**Description**: Export database tables and global settings to SQL, SQLite, or XDB format

### Synopsis
```bash
php spp.php config:export [OPTIONS]
```

---

## `config:import`

**Description**: Import database tables and settings from an exported SQL, SQLite, or XDB file

### Synopsis
```bash
php spp.php config:import [OPTIONS]
```

---

## `config:sync`

**Description**: Synchronize framework configurations (e.g. workflows, dynamic fields) to DB schemas or system registries

### Synopsis
```bash
php spp.php config:sync [OPTIONS]
```

---

## `create:app`

**Description**: Scaffolds a self-contained skeleton app natively (Legacy)

### Synopsis
```bash
php spp.php create:app [OPTIONS]
```

---

## `cron:flush`

**Description**: Clear cron history and lock files

### Synopsis
```bash
php spp.php cron:flush [OPTIONS]
```

---

## `cron:list`

**Description**: List all registered scheduled tasks

### Synopsis
```bash
php spp.php cron:list [OPTIONS]
```

---

## `cron:run`

**Description**: Execute pending cron jobs manually

### Synopsis
```bash
php spp.php cron:run [OPTIONS]
```

---

## `db:verify`

**Description**: Runs the SPP XDB MySQL Compatibility Verification Suite

### Synopsis
```bash
php spp.php db:verify [OPTIONS]
```

---

## `dbsettings:export`

**Description**: Export SPP module DB settings to JSON

### Synopsis
```bash
php spp.php dbsettings:export [OPTIONS]
```

---

## `dbsettings:import`

**Description**: Import SPP module DB settings from JSON

### Synopsis
```bash
php spp.php dbsettings:import [OPTIONS]
```

### Usage
```text
Usage: php spp.php dbsettings:import --file=settings.json [--app=<app_name>]
```

---

## `delete:app`

**Description**: Delete an SPP application context and all its data

### Synopsis
```bash
php spp.php delete:app [OPTIONS]
```

### Usage
```text
Usage: php spp.php delete:app <AppName> [--force]
```

---

## `di:list`

**Description**: List the Dependency Injection container bindings

### Synopsis
```bash
php spp.php di:list [OPTIONS]
```

---

## `diff:apply`

**Description**: Apply a patch or delta file

### Synopsis
```bash
php spp.php diff:apply [OPTIONS]
```

### Usage
```text
Usage: diff:apply --file=patch.json
```

---

## `diff:compare`

**Description**: Compare two JSON arrays or states

### Synopsis
```bash
php spp.php diff:compare [OPTIONS]
```

### Usage
```text
Usage: This command currently requires custom integration to compare specific JSON files.
```

---

## `diff:history`

**Description**: View revision history of an entity

### Synopsis
```bash
php spp.php diff:history [OPTIONS]
```

### Usage
```text
Usage: php spp.php diff:history --type=<ModelClass> --id=<ID>
```

---

## `diff:rollback`

**Description**: Rollback an entity to a previous state

### Synopsis
```bash
php spp.php diff:rollback [OPTIONS]
```

### Usage
```text
Usage: php spp.php diff:rollback --type=<ModelClass> --id=<ID> --rev=<RevID>
```

---

## `drishyam:clear`

**Description**: Clear the Drishyam rendering cache

### Synopsis
```bash
php spp.php drishyam:clear [OPTIONS]
```

---

## `drishyam:compile`

**Description**: Pre-compile Drishyam templates for production

### Synopsis
```bash
php spp.php drishyam:compile [OPTIONS]
```

---

## `drishyam:theme:check`

**Description**: Validate Drishyam theme assets and structure

### Synopsis
```bash
php spp.php drishyam:theme:check [OPTIONS]
```

---

## `ent:delete`

**Description**: Legacy port of ent:delete

### Synopsis
```bash
php spp.php ent:delete [OPTIONS]
```

---

## `ent:edit`

**Description**: Legacy port of ent:edit

### Synopsis
```bash
php spp.php ent:edit [OPTIONS]
```

---

## `ent:list`

**Description**: Legacy port of ent:list

### Synopsis
```bash
php spp.php ent:list [OPTIONS]
```

---

## `ent:manage`

**Description**: Legacy port of ent:manage

### Synopsis
```bash
php spp.php ent:manage [OPTIONS]
```

---

## `ent:query`

**Description**: Legacy port of ent:query

### Synopsis
```bash
php spp.php ent:query [OPTIONS]
```

---

## `ent:show`

**Description**: Legacy port of ent:show

### Synopsis
```bash
php spp.php ent:show [OPTIONS]
```

---

## `env:backup`

**Description**: Backup all environment configurations

### Synopsis
```bash
php spp.php env:backup [OPTIONS]
```

---

## `env:get`

**Description**: Get a specific configuration variable

### Synopsis
```bash
php spp.php env:get [OPTIONS]
```

### Usage
```text
Usage: php spp.php env:get <key> [--app=appname]
```

---

## `env:list`

**Description**: List all environment and configuration variables for an app context

### Synopsis
```bash
php spp.php env:list [OPTIONS]
```

---

## `env:set`

**Description**: Set a specific configuration variable

### Synopsis
```bash
php spp.php env:set [OPTIONS]
```

### Usage
```text
Usage: php spp.php env:set <key> <value> [--app=appname]
```

---

## `env:status`

**Description**: Display system health and environment status

### Synopsis
```bash
php spp.php env:status [OPTIONS]
```

---

## `env:token:rotate`

**Description**: Rotate the system deployment token

### Synopsis
```bash
php spp.php env:token:rotate [OPTIONS]
```

---

## `event:fire`

**Description**: Trigger a specific event manually

### Synopsis
```bash
php spp.php event:fire [OPTIONS]
```

### Usage
```text
Usage: php spp.php event:fire --event=<event_name> [--payload=<json>]
```

---

## `event:list-listeners`

**Description**: List all registered global event listeners

### Synopsis
```bash
php spp.php event:list-listeners [OPTIONS]
```

---

## `ext:disable`

**Description**: Disable a specific extension

### Synopsis
```bash
php spp.php ext:disable [OPTIONS]
```

### Usage
```text
Usage: php spp.php ext:disable <extension_name>
```

---

## `ext:enable`

**Description**: Enable a specific extension

### Synopsis
```bash
php spp.php ext:enable [OPTIONS]
```

### Usage
```text
Usage: php spp.php ext:enable <extension_name>
```

---

## `ext:install`

**Description**: Install an extension from a zip or directory

### Synopsis
```bash
php spp.php ext:install [OPTIONS]
```

### Usage
```text
Usage: php spp.php ext:install --source=<path_or_url>
```

---

## `ext:list`

**Description**: List all available and installed extensions

### Synopsis
```bash
php spp.php ext:list [OPTIONS]
```

---

## `forge`

**Description**: Unified automation and LiveSync engine

### Synopsis
```bash
php spp.php forge [OPTIONS]
```

---

## `frontend:debug`

**Description**: Toggle Frontend CDN development mode (on|off)

### Synopsis
```bash
php spp.php frontend:debug [OPTIONS]
```

---

## `group:create`

**Description**: Create a new shared resource group

### Synopsis
```bash
php spp.php group:create [OPTIONS]
```

### Usage
```text
Usage: php spp.php group:create <group_name> [--extends=core] [--prefix=...]
```

---

## `group:delete`

**Description**: Delete a shared resource group

### Synopsis
```bash
php spp.php group:delete [OPTIONS]
```

### Usage
```text
Usage: php spp.php group:delete <group_name>
```

---

## `group:edit`

**Description**: Edit an existing shared resource group

### Synopsis
```bash
php spp.php group:edit [OPTIONS]
```

### Usage
```text
Usage: php spp.php group:edit <group_name> [--extends=...] [--prefix=...]
```

---

## `group:list`

**Description**: List all shared resource groups

### Synopsis
```bash
php spp.php group:list [OPTIONS]
```

---

## `i18n:export`

**Description**: Export translations for a specific locale to a JSON file.

### Synopsis
```bash
php spp.php i18n:export [OPTIONS]
```

---

## `i18n:import`

**Description**: Import translations from a JSON file into the database.

### Synopsis
```bash
php spp.php i18n:import [OPTIONS]
```

---

## `import:component`

**Description**: Imports pristine air-gapped sovereign UI components

### Synopsis
```bash
php spp.php import:component [OPTIONS]
```

---

## `interdb:config`

**Description**: Get or set the interdb operating mode

### Synopsis
```bash
php spp.php interdb:config [OPTIONS]
```

---

## `interdb:mapping:add`

**Description**: Add a new InterDB mapping

### Synopsis
```bash
php spp.php interdb:mapping:add [OPTIONS]
```

### Usage
```text
Usage: php spp.php interdb:mapping:add <alias> <engine> <table>
```

---

## `interdb:mapping:list`

**Description**: List all InterDB mappings

### Synopsis
```bash
php spp.php interdb:mapping:list [OPTIONS]
```

---

## `interdb:mapping:remove`

**Description**: Remove an InterDB mapping

### Synopsis
```bash
php spp.php interdb:mapping:remove [OPTIONS]
```

### Usage
```text
Usage: php spp.php interdb:mapping:remove <alias>
```

---

## `lang:list`

**Description**: List all translations

### Synopsis
```bash
php spp.php lang:list [OPTIONS]
```

---

## `lang:scan`

**Description**: Scan directories for new translation keys

### Synopsis
```bash
php spp.php lang:scan [OPTIONS]
```

---

## `lang:set`

**Description**: Set a translation for a key

### Synopsis
```bash
php spp.php lang:set [OPTIONS]
```

### Usage
```text
Usage: php spp.php lang:set <key> <locale> <translation>
```

---

## `lekhak:generate-docs`

**Description**: Generates documentation nodes for SPP Core and Modules.

### Synopsis
```bash
php spp.php lekhak:generate-docs [OPTIONS]
```

---

## `lekhak:setup`

**Description**: Initializes Lekhak CMS database tables.

### Synopsis
```bash
php spp.php lekhak:setup [OPTIONS]
```

---

## `list`

**Description**: Lists all discovered SPP CLI commands.

### Synopsis
```bash
php spp.php list [OPTIONS]
```

---

## `live:status`

**Description**: Check the status of websocket/polling servers

### Synopsis
```bash
php spp.php live:status [OPTIONS]
```

---

## `live:trigger`

**Description**: Push a live event to clients

### Synopsis
```bash
php spp.php live:trigger [OPTIONS]
```

### Usage
```text
Usage: php spp.php live:trigger --channel=<channel> --event=<event> [--payload=<json>]
```

---

## `logger:clear`

**Description**: Clear the SPP application logs

### Synopsis
```bash
php spp.php logger:clear [OPTIONS]
```

---

## `logger:tail`

**Description**: Tail the SPP application log file

### Synopsis
```bash
php spp.php logger:tail [OPTIONS]
```

---

## `make:app`

**Description**: Create a new SPP application context

### Synopsis
```bash
php spp.php make:app [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:app <AppName> <Type>
```

---

## `make:blade-project`

**Description**: Scaffold a new Blade-enabled SPP application

### Synopsis
```bash
php spp.php make:blade-project [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:blade-project <app_name>
```

---

## `make:blade-scaffold`

**Description**: Create a full stack Blade scaffold (Entity, YAML Form, Controller, Blade Views)

### Synopsis
```bash
php spp.php make:blade-scaffold [OPTIONS]
```

---

## `make:command`

**Description**: Create a new CLI command class

### Synopsis
```bash
php spp.php make:command [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:command <name> [--app=appname] [--command=cmd:name]
```

---

## `make:controller`

**Description**: Create a new controller class

### Synopsis
```bash
php spp.php make:controller [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:controller <name> [--app=appname] [--resource]
```

---

## `make:drupal-bridge`

**Description**: Scaffold a Drupal module to bridge SPP into Drupal

### Synopsis
```bash
php spp.php make:drupal-bridge [OPTIONS]
```

---

## `make:entity`

**Description**: Create a new SPPEntity definition

### Synopsis
```bash
php spp.php make:entity [OPTIONS]
```

---

## `make:form`

**Description**: Create a new SPP form definition

### Synopsis
```bash
php spp.php make:form [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:form <name> [--type=yml|xml]
```

---

## `make:go-service`

**Description**: Create a new Go service script

### Synopsis
```bash
php spp.php make:go-service [OPTIONS]
```

### Usage
```text
Usage: spp make:go-service <name> [--app=context]
```

---

## `make:middleware`

**Description**: Create a new middleware class

### Synopsis
```bash
php spp.php make:middleware [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:middleware <name> [--app=appname]
```

---

## `make:model`

**Description**: Create a new model class (Fluent-ready)

### Synopsis
```bash
php spp.php make:model [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:model <name> [--app=appname] [--table=tablename]
```

---

## `make:module`

**Description**: Create a new SPP module

### Synopsis
```bash
php spp.php make:module [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:module <name> [--scope=spp|contrib|app]
```

---

## `make:python-service`

**Description**: Create a new Python service script

### Synopsis
```bash
php spp.php make:python-service [OPTIONS]
```

### Usage
```text
Usage: spp make:python-service <name> [--app=context]
```

---

## `make:react-component`

**Description**: Scaffold a new React component (ESM/No-build)

### Synopsis
```bash
php spp.php make:react-component [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:react-component <ComponentName>
```

---

## `make:scaffold`

**Description**: Create a full stack scaffold (Entity, DB, Controller, View)

### Synopsis
```bash
php spp.php make:scaffold [OPTIONS]
```

---

## `make:service`

**Description**: Create a new service class

### Synopsis
```bash
php spp.php make:service [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:service <name> [--app=appname]
```

---

## `make:ux-component`

**Description**: Scaffold a new SPP-UX reactive component

### Synopsis
```bash
php spp.php make:ux-component [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:ux-component <ComponentName>
```

---

## `make:view`

**Description**: Create a new view definition (equivalent to Drupal Views).

### Synopsis
```bash
php spp.php make:view [OPTIONS]
```

---

## `make:vue-component`

**Description**: Scaffold a new Vue 3 component (ESM/No-build)

### Synopsis
```bash
php spp.php make:vue-component [OPTIONS]
```

### Usage
```text
Usage: php spp.php make:vue-component <ComponentName>
```

---

## `man`

**Description**: Format and display manual pages for SPP commands

### Synopsis
```bash
php spp.php man [OPTIONS]
```

---

## `man:generate`

**Description**: Generate man-pages in Markdown and UNIX roff formats

### Synopsis
```bash
php spp.php man:generate [OPTIONS]
```

---

## `manifest:export`

**Description**: Exports tool autodiscovery definitions for AI Copilots

### Synopsis
```bash
php spp.php manifest:export [OPTIONS]
```

---

## `marketing:campaign:dispatch`

**Description**: Dispatch a marketing campaign manually

### Synopsis
```bash
php spp.php marketing:campaign:dispatch [OPTIONS]
```

### Usage
```text
Usage: php spp.php marketing:campaign:dispatch --id=<campaign_id>
```

---

## `marketing:campaign:sync`

**Description**: Synchronize marketing campaigns/templates with external CRMs

### Synopsis
```bash
php spp.php marketing:campaign:sync [OPTIONS]
```

---

## `middleware:list`

**Description**: List the middleware pipeline for an app

### Synopsis
```bash
php spp.php middleware:list [OPTIONS]
```

---

## `migrate:deploy`

**Description**: Pushes local app state and configurations to a remote SPPMigrate instance

### Synopsis
```bash
php spp.php migrate:deploy [OPTIONS]
```

### Usage
```text
Usage: php spp.php migrate:deploy <target_uri> [--full] [--key=YOUR_API_KEY]
```

---

## `module:list`

**Description**: Discovers and tabulates active kernel framework modules

### Synopsis
```bash
php spp.php module:list [OPTIONS]
```

---

## `polyglot:list`

**Description**: Discovers and tabulates all registered polyglot services

### Synopsis
```bash
php spp.php polyglot:list [OPTIONS]
```

---

## `polyglot:run`

**Description**: Executes a specific polyglot service directly

### Synopsis
```bash
php spp.php polyglot:run [OPTIONS]
```

### Usage
```text
Usage: php spp.php polyglot:run --path=<relative_path_to_service> [args...]
```

---

## `polyglot:status`

**Description**: Checks the runtime environment for polyglot language binaries

### Synopsis
```bash
php spp.php polyglot:status [OPTIONS]
```

---

## `profile:report:generate`

**Description**: Dump a performance profile trace for debugging

### Synopsis
```bash
php spp.php profile:report:generate [OPTIONS]
```

---

## `profile:status`

**Description**: Check if the performance profiler is running/enabled

### Synopsis
```bash
php spp.php profile:status [OPTIONS]
```

---

## `pwa:build`

**Description**: Full build for PWA assets

### Synopsis
```bash
php spp.php pwa:build [OPTIONS]
```

---

## `pwa:manifest`

**Description**: Generate or update the manifest.json

### Synopsis
```bash
php spp.php pwa:manifest [OPTIONS]
```

---

## `pwa:service-worker-gen`

**Description**: Regenerate the service worker script

### Synopsis
```bash
php spp.php pwa:service-worker-gen [OPTIONS]
```

---

## `queue:list`

**Description**: List all jobs currently in the queue

### Synopsis
```bash
php spp.php queue:list [OPTIONS]
```

---

## `queue:work`

**Description**: Starts a worker loop to process background jobs from the queue.

### Synopsis
```bash
php spp.php queue:work [OPTIONS]
```

---

## `serve`

**Description**: Start a local development server for the current application

### Synopsis
```bash
php spp.php serve [OPTIONS]
```

---

## `session:clean`

**Description**: Clean up expired sessions

### Synopsis
```bash
php spp.php session:clean [OPTIONS]
```

---

## `session:destroy-all`

**Description**: Invalidate all active sessions across the application

### Synopsis
```bash
php spp.php session:destroy-all [OPTIONS]
```

---

## `site:install`

**Description**: Initialize the database and load default configurations for a specific profile.

### Synopsis
```bash
php spp.php site:install [OPTIONS]
```

---

## `storage:clean`

**Description**: Clean up temporary files in storage

### Synopsis
```bash
php spp.php storage:clean [OPTIONS]
```

---

## `storage:link`

**Description**: Create symbolic links for public storage

### Synopsis
```bash
php spp.php storage:link [OPTIONS]
```

---

## `storage:sync`

**Description**: Sync local storage with external disks (stub)

### Synopsis
```bash
php spp.php storage:sync [OPTIONS]
```

---

## `sys:bridge:info`

**Description**: Legacy port of sys:bridge:info

### Synopsis
```bash
php spp.php sys:bridge:info [OPTIONS]
```

---

## `sys:bridge:setup`

**Description**: Legacy port of sys:bridge:setup

### Synopsis
```bash
php spp.php sys:bridge:setup [OPTIONS]
```

---

## `sys:debug`

**Description**: Toggle global framework debug mode (on|off)

### Synopsis
```bash
php spp.php sys:debug [OPTIONS]
```

### Usage
```text
Usage: php spp.php sys:debug on|off
```

---

## `sys:info`

**Description**: Legacy port of sys:info

### Synopsis
```bash
php spp.php sys:info [OPTIONS]
```

---

## `sys:test:auto`

**Description**: Runs Automated Evolutionary Testing (Parikshak) for the current application.

### Synopsis
```bash
php spp.php sys:test:auto [OPTIONS]
```

---

## `sys:update`

**Description**: Legacy port of sys:update

### Synopsis
```bash
php spp.php sys:update [OPTIONS]
```

---

## `test:blueprint`

**Description**: Generate a structural blueprint for an entity

### Synopsis
```bash
php spp.php test:blueprint [OPTIONS]
```

### Usage
```text
Usage: php spp.php test:blueprint <EntityClass>
```

---

## `test:monkey`

**Description**: Runs chaos monkey / fuzzing scenarios for an entity

### Synopsis
```bash
php spp.php test:monkey [OPTIONS]
```

### Usage
```text
Usage: php spp.php test:monkey <EntityClass>
```

---

## `test:run`

**Description**: Runs Parikshak evaluation for an entity or the whole suite

### Synopsis
```bash
php spp.php test:run [OPTIONS]
```

---

## `theme:activate`

**Description**: Switch the active theme adapter (native/wp/joomla) and optionally set the theme name

### Synopsis
```bash
php spp.php theme:activate [OPTIONS]
```

---

## `ui:build`

**Description**: Legacy port of ui:build

### Synopsis
```bash
php spp.php ui:build [OPTIONS]
```

---

## `ui:comp:php`

**Description**: Legacy port of ui:comp:php

### Synopsis
```bash
php spp.php ui:comp:php [OPTIONS]
```

---

## `ui:serv`

**Description**: Legacy port of ui:serv

### Synopsis
```bash
php spp.php ui:serv [OPTIONS]
```

---

## `ui:store`

**Description**: Legacy port of ui:store

### Synopsis
```bash
php spp.php ui:store [OPTIONS]
```

---

## `ui:view`

**Description**: Legacy port of ui:view

### Synopsis
```bash
php spp.php ui:view [OPTIONS]
```

---

## `ui:watch`

**Description**: Legacy port of ui:watch

### Synopsis
```bash
php spp.php ui:watch [OPTIONS]
```

---

## `userprofile:export`

**Description**: Export user profile data for compliance/GDPR

### Synopsis
```bash
php spp.php userprofile:export [OPTIONS]
```

### Usage
```text
Usage: php spp.php userprofile:export --user=<user_id>
```

---

## `userprofile:schema:update`

**Description**: Sync extended user profile metadata schemas

### Synopsis
```bash
php spp.php userprofile:schema:update [OPTIONS]
```

---

## `ux:debug`

**Description**: Toggle SPP-UX verbose logging (on|off)

### Synopsis
```bash
php spp.php ux:debug [OPTIONS]
```

---

## `verify:sovereignty`

**Description**: Validates complete stack self-containment/zero external links

### Synopsis
```bash
php spp.php verify:sovereignty [OPTIONS]
```

---

## `view:page:add`

**Description**: Add a new page route to an app

### Synopsis
```bash
php spp.php view:page:add [OPTIONS]
```

### Usage
```text
Usage: php spp.php view:page:add --name=<route> --url=<target> [--app=default] [--source=yaml|db]
```

---

## `view:page:list`

**Description**: List all registered pages/routes for an app

### Synopsis
```bash
php spp.php view:page:list [OPTIONS]
```

---

## `view:page:remove`

**Description**: Remove a page route from an app

### Synopsis
```bash
php spp.php view:page:remove [OPTIONS]
```

### Usage
```text
Usage: php spp.php view:page:remove --name=<route> [--app=default] [--source=yaml|db]
```

---

## `view:service:add`

**Description**: Register a new AJAX service endpoint

### Synopsis
```bash
php spp.php view:service:add [OPTIONS]
```

### Usage
```text
Usage: php spp.php view:service:add --name=<service> --script=<path> [--method=POST] [--app=default] [--source=yaml|db]
```

---

## `view:service:list`

**Description**: List all registered AJAX services for an app

### Synopsis
```bash
php spp.php view:service:list [OPTIONS]
```

---

## `view:service:remove`

**Description**: Remove an AJAX service endpoint from an app

### Synopsis
```bash
php spp.php view:service:remove [OPTIONS]
```

### Usage
```text
Usage: php spp.php view:service:remove --name=<service> [--app=default] [--source=yaml|db]
```

---

## `view:service:test`

**Description**: Test an AJAX service endpoint from the CLI

### Synopsis
```bash
php spp.php view:service:test [OPTIONS]
```

### Usage
```text
Usage: php spp.php view:service:test --name=<service> [--app=default] [--payload=
```

---

## `wizard:list`

**Description**: List all registered multi-step wizard definitions

### Synopsis
```bash
php spp.php wizard:list [OPTIONS]
```

---

## `wizard:validate`

**Description**: Validate the schema and steps of a wizard configuration

### Synopsis
```bash
php spp.php wizard:validate [OPTIONS]
```

### Usage
```text
Usage: php spp.php wizard:validate --id=<wizard_id>
```

---

## `xdb:describe`

**Description**: Describe the schema of an XDB table

### Synopsis
```bash
php spp.php xdb:describe [OPTIONS]
```

### Usage
```text
Usage: php spp xdb:describe <table_name> [--db=dbname]
```

---

## `xdb:list-dbs`

**Description**: List all available XDB databases

### Synopsis
```bash
php spp.php xdb:list-dbs [OPTIONS]
```

---

## `xdb:list-tables`

**Description**: List all tables in an XDB database

### Synopsis
```bash
php spp.php xdb:list-tables [OPTIONS]
```

---

## `xdb:query`

**Description**: Execute a SQL or XPath query on the XML database

### Synopsis
```bash
php spp.php xdb:query [OPTIONS]
```

### Usage
```text
Usage: php spp xdb:query \
```

---

## `xdb:shell`

**Description**: Launch the interactive SPPXDB shell

### Synopsis
```bash
php spp.php xdb:shell [OPTIONS]
```

---



## SPP Polyglot Architecture

The SPP Polyglot engine provides a seamless bridge between PHP and Python, Perl, Node.js, C++, Java, .NET, and Go. It runs in two modes: Ephemeral (per-request) and Daemon (persistent socket background process).

### For Framework Developers
- **Path Structure**: All language bindings, including headers (polyglot.hpp), packages (polyglot.go), and dynamic dispatchers (dispatch.py, dispatch.pl, dispatch.js), reside strictly under spp/lib/polyglot/ and its sibling directories.
- **Daemon Port Binding**: Daemons spawn dynamically via proc_open (
ohup & on POSIX, .vbs wrapper on Windows) and write their active TCP port to ar/shared/bridge/daemons/[hash].port.
- **Dependency Injection**: Use \SPP\PolyglotBridge::call() to interface natively with the language of choice. JSON payloads are passed via standard I/O (ephemeral) or TCP sockets (daemon) automatically.

### For Newcomers
Imagine SPP as a universal translator. You can write your AI scripts in Python, your heavy math in C++, and your web server in PHP. The **Polyglot Engine** lets PHP seamlessly talk to these other languages as if they were written in PHP itself! You just type a PHP command, and in the background, SPP wakes up your Python or C++ code, hands it your data, and brings the answer right back to you instantly.

