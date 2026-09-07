# SPP CLI Manual

Detailed reference for all `spp.php` commands, generated via static code analysis.

## Table of Contents
- [`ai:benchmark:models`](#aibenchmarkmodels)
- [`ai:make:workflow`](#aimakeworkflow)
- [`ai:prompt`](#aiprompt)
- [`ai:providers`](#aiproviders)
- [`ai:refactor:enterprise`](#airefactorenterprise)
- [`api:key:generate`](#apikeygenerate)
- [`api:key:revoke`](#apikeyrevoke)
- [`api:route:list`](#apiroutelist)
- [`app:config`](#appconfig)
- [`app:default`](#appdefault)
- [`app:list`](#applist)
- [`app:quota`](#appquota)
- [`app:set-base`](#appsetbase)
- [`ask`](#ask)
- [`audit:lineage`](#auditlineage)
- [`auth:magiclink`](#authmagiclink)
- [`auth:tokens`](#authtokens)
- [`blade:clear`](#bladeclear)
- [`blade:view`](#bladeview)
- [`bridge:call`](#bridgecall)
- [`cache:clear`](#cacheclear)
- [`cache:compile-registry`](#cachecompileregistry)
- [`cache:prune`](#cacheprune)
- [`cache:purge`](#cachepurge)
- [`cache:stats`](#cachestats)
- [`cache:warmup`](#cachewarmup)
- [`clear:aicache`](#clearaicache)
- [`clear:cache`](#clearcache)
- [`component:crud`](#componentcrud)
- [`config`](#config)
- [`config:export`](#configexport)
- [`config:import`](#configimport)
- [`config:sync`](#configsync)
- [`cron:flush`](#cronflush)
- [`cron:list`](#cronlist)
- [`cron:run`](#cronrun)
- [`db:migration:verify-zero-downtime`](#dbmigrationverifyzerodowntime)
- [`db:sync`](#dbsync)
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
- [`deploy:token:rotate`](#deploytokenrotate)
- [`di:list`](#dilist)
- [`diff:apply`](#diffapply)
- [`diff:compare`](#diffcompare)
- [`diff:history`](#diffhistory)
- [`diff:rollback`](#diffrollback)
- [`docs:api`](#docsapi)
- [`docs:build`](#docsbuild)
- [`docs:man`](#docsman)
- [`docs:openapi`](#docsopenapi)
- [`docs:phpdoc`](#docsphpdoc)
- [`doctor`](#doctor)
- [`drishyam:clear`](#drishyamclear)
- [`drishyam:compile`](#drishyamcompile)
- [`drishyam:theme:check`](#drishyamthemecheck)
- [`ent:edit`](#entedit)
- [`entity:crud`](#entitycrud)
- [`env:backup`](#envbackup)
- [`env:get`](#envget)
- [`env:list`](#envlist)
- [`env:mode`](#envmode)
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
- [`iam:abac`](#iamabac)
- [`iam:roles`](#iamroles)
- [`import:component`](#importcomponent)
- [`integration:install`](#integrationinstall)
- [`integration:queue:work`](#integrationqueuework)
- [`integration:restore`](#integrationrestore)
- [`integration:seed`](#integrationseed)
- [`interdb:config`](#interdbconfig)
- [`interdb:mapping:add`](#interdbmappingadd)
- [`interdb:mapping:list`](#interdbmappinglist)
- [`interdb:mapping:remove`](#interdbmappingremove)
- [`issue`](#issue)
- [`kernel:compile`](#kernelcompile)
- [`lang:export`](#langexport)
- [`lang:import`](#langimport)
- [`lang:list`](#langlist)
- [`lang:scan`](#langscan)
- [`lang:set`](#langset)
- [`lekhak:generate-docs`](#lekhakgeneratedocs)
- [`lekhak:setup`](#lekhaksetup)
- [`lint`](#lint)
- [`list`](#list)
- [`live:status`](#livestatus)
- [`live:trigger`](#livetrigger)
- [`logger:clear`](#loggerclear)
- [`logger:tail`](#loggertail)
- [`make:app`](#makeapp)
- [`make:app-legacy`](#makeapplegacy)
- [`make:blade`](#makeblade)
- [`make:blade-project`](#makebladeproject)
- [`make:blade-scaffold`](#makebladescaffold)
- [`make:command`](#makecommand)
- [`make:command-test`](#makecommandtest)
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
- [`make:partial`](#makepartial)
- [`make:perl-service`](#makeperlservice)
- [`make:python-service`](#makepythonservice)
- [`make:react-component`](#makereactcomponent)
- [`make:report`](#makereport)
- [`make:scaffold`](#makescaffold)
- [`make:seeder`](#makeseeder)
- [`make:service`](#makeservice)
- [`make:sppview`](#makesppview)
- [`make:stream`](#makestream)
- [`make:twig`](#maketwig)
- [`make:ux-component`](#makeuxcomponent)
- [`make:view`](#makeview)
- [`make:vue-component`](#makevuecomponent)
- [`make:wizard`](#makewizard)
- [`man`](#man)
- [`man:generate`](#mangenerate)
- [`manifest:export`](#manifestexport)
- [`mesh:add`](#meshadd)
- [`mesh:list`](#meshlist)
- [`mesh:remove`](#meshremove)
- [`mesh:update`](#meshupdate)
- [`middleware:list`](#middlewarelist)
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
- [`oauth:client:create`](#oauthclientcreate)
- [`oauth:client:delete`](#oauthclientdelete)
- [`oauth:client:list`](#oauthclientlist)
- [`optimize:ux`](#optimizeux)
- [`pkg:install`](#pkginstall)
- [`profile:report:generate`](#profilereportgenerate)
- [`profile:status`](#profilestatus)
- [`queue:list`](#queuelist)
- [`queue:work`](#queuework)
- [`role:create`](#rolecreate)
- [`role:list`](#rolelist)
- [`schedule:run`](#schedulerun)
- [`scim:test:user`](#scimtestuser)
- [`serve`](#serve)
- [`serve:async`](#serveasync)
- [`service:crud`](#servicecrud)
- [`session:clean`](#sessionclean)
- [`session:destroy-all`](#sessiondestroyall)
- [`shell`](#shell)
- [`site:install`](#siteinstall)
- [`sppdocs:audit:verify`](#sppdocsauditverify)
- [`sppdocs:storage:migrate`](#sppdocsstoragemigrate)
- [`storage:clean`](#storageclean)
- [`storage:link`](#storagelink)
- [`storage:sync`](#storagesync)
- [`sys:debug`](#sysdebug)
- [`sys:seed`](#sysseed)
- [`sys:status`](#sysstatus)
- [`sys:test:auto`](#systestauto)
- [`sys:upgrade`](#sysupgrade)
- [`test`](#test)
- [`test:blueprint`](#testblueprint)
- [`test:dry-run`](#testdryrun)
- [`test:module`](#testmodule)
- [`test:monkey`](#testmonkey)
- [`test:routes`](#testroutes)
- [`test:run`](#testrun)
- [`theme:activate`](#themeactivate)
- [`tinker`](#tinker)
- [`ui:add`](#uiadd)
- [`user:create`](#usercreate)
- [`user:delete`](#userdelete)
- [`user:list`](#userlist)
- [`user:password:reset`](#userpasswordreset)
- [`userprofile:export`](#userprofileexport)
- [`userprofile:schema:update`](#userprofileschemaupdate)
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
- [`workflow:dump`](#workflowdump)
- [`workflow:process-timeouts`](#workflowprocesstimeouts)
- [`xdb:describe`](#xdbdescribe)
- [`xdb:list-dbs`](#xdblistdbs)
- [`xdb:list-tables`](#xdblisttables)
- [`xdb:make:migration`](#xdbmakemigration)
- [`xdb:make:seeder`](#xdbmakeseeder)
- [`xdb:migrate`](#xdbmigrate)
- [`xdb:query`](#xdbquery)
- [`xdb:seed`](#xdbseed)
- [`xdb:shell`](#xdbshell)

---

## `ai:benchmark:models`

**Description**: Benchmark configured AI models (Ollama, OpenAI, Anthropic) for tool calling latency and schema accuracy

### Synopsis
```bash
php spp.php ai:benchmark:models [OPTIONS]
```

### Options
- `--provider=` : Expects a value. Extracted via static analysis from AIBenchmarkCommand.php
- `--models=` : Expects a value. Extracted via static analysis from AIBenchmarkCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `ai:make:workflow`

**Description**: Synthesize natural language business requirements into valid sppworkflow YAML definitions

### Synopsis
```bash
php spp.php ai:make:workflow [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ai:make:workflow <workflow_name> \
```

### Options
- `--provider=` : Expects a value. Extracted via static analysis from MakeAiWorkflowCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Dynamically loads kernel modules: sppai.
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `ai:prompt`

**Description**: Send a prompt to the AI provider

### Synopsis
```bash
php spp.php ai:prompt [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ai:prompt \
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from AiPromptCommand.php
- `--provider=` : Expects a value. Extracted via static analysis from AiPromptCommand.php
- `--model=` : Expects a value. Extracted via static analysis from AiPromptCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: sppai.
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `ai:providers`

**Description**: List all registered AI providers

### Synopsis
```bash
php spp.php ai:providers [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from AiProvidersCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: sppai.
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `ai:refactor:enterprise`

**Description**: AI-powered automated refactoring daemon to modernize legacy code into strict SPP enterprise compliance

### Synopsis
```bash
php spp.php ai:refactor:enterprise [OPTIONS]
```

### Options
- `--path=` : Expects a value. Extracted via static analysis from RefactorEnterpriseCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `api:key:generate`

**Description**: Generates a new permanent API Key.

### Synopsis
```bash
php spp.php api:key:generate [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: permanent, \SPPMod\SPPDB\SPPDB.


---

## `api:key:revoke`

**Description**: Revoke an existing API token

### Synopsis
```bash
php spp.php api:key:revoke [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php api:key:revoke --token=<token>

```

### Options
- `--token=` : Expects a value. Extracted via static analysis from ApiKeyRevokeCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `api:route:list`

**Description**: Tabulate all exposed REST API routes

### Synopsis
```bash
php spp.php api:route:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `app:config`

**Description**: Configure application settings (e.g., base_url, table_prefix)

### Synopsis
```bash
php spp.php app:config [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php app:config <app_name> [--base_url=...] [--table_prefix=...]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `app:default`

**Description**: Set or view the default global CLI application context

### Synopsis
```bash
php spp.php app:default [OPTIONS]
```

### Options
- `--set=` : Expects a value. Extracted via static analysis from AppDefaultCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `app:list`

**Description**: List all registered SPP applications

### Synopsis
```bash
php spp.php app:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `app:quota`

**Description**: Set hardware resource limits for a Guest App in the WebOS Registry. Usage: app:quota <alias> [--ram=...] [--cpu=...]

### Synopsis
```bash
php spp.php app:quota [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php app:quota <alias> [--ram=...] [--cpu=...]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `app:set-base`

**Description**: Set an application as the primary/base application

### Synopsis
```bash
php spp.php app:set-base [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php app:set-base <app_name>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `ask`

**Description**: Ask the SPP AI Mentor a question about the framework.

### Synopsis
```bash
php spp.php ask [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ask \
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \Exception, \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `audit:lineage`

**Description**: Traverses and verifies cryptographic Merkle-DAG trace logs

### Synopsis
```bash
php spp.php audit:lineage [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from AuditLineageCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `auth:magiclink`

**Description**: Generate a one-time passwordless Magic Link for a user

### Synopsis
```bash
php spp.php auth:magiclink [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB, SPPUser.


---

## `auth:tokens`

**Description**: Manage Personal Access Tokens for API Authentication

### Synopsis
```bash
php spp.php auth:tokens [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: SPPUser, SPPDB.


---

## `blade:clear`

**Description**: Clear the compiled Blade view cache

### Synopsis
```bash
php spp.php blade:clear [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `blade:view`

**Description**: Manage Blade views (list, create, delete)

### Synopsis
```bash
php spp.php blade:view [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `bridge:call`

**Description**: Internal RPC bridge to invoke PHP methods from Polyglot clients

### Synopsis
```bash
php spp.php bridge:call [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \ReflectionMethod.


---

## `cache:clear`

**Description**: Clear the application file/redis cache

### Synopsis
```bash
php spp.php cache:clear [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `cache:compile-registry`

**Description**: Rebuilds the Orion Cache and System Registry

### Synopsis
```bash
php spp.php cache:compile-registry [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \SPP\EventParams.


---

## `cache:prune`

**Description**: Prune expired cache items from storage

### Synopsis
```bash
php spp.php cache:prune [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CachePruneCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `cache:purge`

**Description**: Purge cache tags or URLs from the reverse proxy (Varnish/CDN).

### Synopsis
```bash
php spp.php cache:purge [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes external system binaries or shell commands.


---

## `cache:stats`

**Description**: Display cache driver statistics

### Synopsis
```bash
php spp.php cache:stats [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CacheStatsCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `cache:warmup`

**Description**: Warm up common application caches

### Synopsis
```bash
php spp.php cache:warmup [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CacheWarmupCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `clear:aicache`

**Description**: Clears the WebOS AI Decision cache.

### Synopsis
```bash
php spp.php clear:aicache [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php clear:aicache

```

### Options
- `--help` : Boolean flag. Extracted via static analysis from ClearAiCacheCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `clear:cache`

**Description**: Clear the application file/redis cache

### Synopsis
```bash
php spp.php clear:cache [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `component:crud`

**Description**: Manage SPP UI components (list, create, edit, delete)

### Synopsis
```bash
php spp.php component:crud [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `config`

**Description**: Manage framework and application configuration

### Synopsis
```bash
php spp.php config [OPTIONS]
```

### Extended Usage
```text
Usage: spp config [get|set|delete|list|cache|clear] [key] [value]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `config:export`

**Description**: Export database tables and global settings to SQL, SQLite, or XDB format

### Synopsis
```bash
php spp.php config:export [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDB\SPPDB, \PDO, \DOMDocument.


---

## `config:import`

**Description**: Import database tables and settings from an exported SQL, SQLite, or XDB file

### Synopsis
```bash
php spp.php config:import [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDB\SPPDB, \PDO, \DOMDocument.


---

## `config:sync`

**Description**: Synchronize framework configurations (e.g. workflows, dynamic fields) to DB schemas or system registries

### Synopsis
```bash
php spp.php config:sync [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `cron:flush`

**Description**: Clear cron history and lock files

### Synopsis
```bash
php spp.php cron:flush [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CronFlushCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `cron:list`

**Description**: List all registered scheduled tasks

### Synopsis
```bash
php spp.php cron:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CronListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.


---

## `cron:run`

**Description**: Execute pending cron jobs manually

### Synopsis
```bash
php spp.php cron:run [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CronRunCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPP\CLI\Commands\WorkflowProcessTimeoutsCommand.


---

## `db:migration:verify-zero-downtime`

**Description**: Perform a dry-run analysis of database migration DDL statements to verify zero-downtime compliance and schema safety

### Synopsis
```bash
php spp.php db:migration:verify-zero-downtime [OPTIONS]
```

### Options
- `--path=` : Expects a value. Extracted via static analysis from VerifyZeroDowntimeCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `db:sync`

**Description**: Synchronize data between two database adapters (e.g. MySQL to XDB)

### Synopsis
```bash
php spp.php db:sync [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php db:sync --from=[engine:table] --to=[engine:table]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPPDB.


---

## `db:verify`

**Description**: Runs the SPP XDB MySQL Compatibility Verification Suite

### Synopsis
```bash
php spp.php db:verify [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes external system binaries or shell commands.


---

## `dbsettings:export`

**Description**: Export SPP module DB settings to JSON

### Synopsis
```bash
php spp.php dbsettings:export [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from DBSettingsExportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `dbsettings:import`

**Description**: Import SPP module DB settings from JSON

### Synopsis
```bash
php spp.php dbsettings:import [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php dbsettings:import --file=settings.json [--app=<app_name>]

```

### Options
- `--file=` : Expects a value. Extracted via static analysis from DBSettingsImportCommand.php
- `--app=` : Expects a value. Extracted via static analysis from DBSettingsImportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `delete:app`

**Description**: Delete an SPP application context and all its data (files, config, caches, views)

### Synopsis
```bash
php spp.php delete:app [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php delete:app <AppName> [--force] [--keep-db] [--dry-run]

```

### Options
- `--help` : Boolean flag. Extracted via static analysis from DeleteAppCommand.php
- `--force` : Boolean flag. Extracted via static analysis from DeleteAppCommand.php
- `--keep-db` : Boolean flag. Extracted via static analysis from DeleteAppCommand.php
- `--dry-run` : Boolean flag. Extracted via static analysis from DeleteAppCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \PDO, \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `deploy:backups`

**Description**: List available snapshot backups on a remote target for rollback

### Synopsis
```bash
php spp.php deploy:backups [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployBackupsCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `deploy:build`

**Description**: Create a local deployment artifact bundle without pushing

### Synopsis
```bash
php spp.php deploy:build [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:build <target_uri> [--key=YOUR_API_KEY] [--no-db] [--no-files]

```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployBuildCommand.php
- `--no-db` : Boolean flag. Extracted via static analysis from DeployBuildCommand.php
- `--no-files` : Boolean flag. Extracted via static analysis from DeployBuildCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \SPPMod\SPPDeploy\Scanner\ProjectScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \ZipArchive, \Exception, \SPPMod\SPPDB\SPPDB.


---

## `deploy:cleanup`

**Description**: Prune old rollback snapshots from the remote target server

### Synopsis
```bash
php spp.php deploy:cleanup [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployCleanupCommand.php
- `--keep=` : Expects a value. Extracted via static analysis from DeployCleanupCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `deploy:cluster`

**Description**: Deploy to a multi-server cluster sequentially

### Synopsis
```bash
php spp.php deploy:cluster [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:cluster <cluster_name>

```

### Options
- `--force` : Boolean flag. Extracted via static analysis from DeployClusterCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: DeployPushCommand.


---

## `deploy:env`

**Description**: Manage remote environment variables securely

### Synopsis
```bash
php spp.php deploy:env [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:env [target_uri] push --key=MY_KEY --value=MY_VALUE [--key_api=YOUR_API_KEY]

```

### Options
- `--key_api=` : Expects a value. Extracted via static analysis from DeployEnvCommand.php
- `--key=` : Expects a value. Extracted via static analysis from DeployEnvCommand.php
- `--value=` : Expects a value. Extracted via static analysis from DeployEnvCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `deploy:history`

**Description**: 

### Synopsis
```bash
php spp.php deploy:history [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployHistoryCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `deploy:init`

**Description**: 

### Synopsis
```bash
php spp.php deploy:init [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `deploy:logs`

**Description**: View and tail remote application error logs securely over HTTP

### Synopsis
```bash
php spp.php deploy:logs [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployLogsCommand.php
- `--lines=` : Expects a value. Extracted via static analysis from DeployLogsCommand.php
- `--tail` : Boolean flag. Extracted via static analysis from DeployLogsCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `deploy:maintenance`

**Description**: Toggle manual maintenance mode on a remote target or local environment

### Synopsis
```bash
php spp.php deploy:maintenance [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployMaintenanceCommand.php
- `--on` : Boolean flag. Extracted via static analysis from DeployMaintenanceCommand.php
- `--off` : Boolean flag. Extracted via static analysis from DeployMaintenanceCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `deploy:plan`

**Description**: Perform a dry run to view file changes and raw database SQL diffs before deploying

### Synopsis
```bash
php spp.php deploy:plan [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:plan <target_uri> [--key=YOUR_API_KEY] [--no-db]

```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployPlanCommand.php
- `--no-db` : Boolean flag. Extracted via static analysis from DeployPlanCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDeploy\Scanner\FileScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \SPPMod\SPPDB\SPPDB.


---

## `deploy:pull`

**Description**: 

### Synopsis
```bash
php spp.php deploy:pull [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployPullCommand.php
- `--force` : Boolean flag. Extracted via static analysis from DeployPullCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \ZipArchive, \Exception, \SPPMod\SPPDB\SPPDB.


---

## `deploy:push`

**Description**: Push the local project state to a remote SPP target server

### Synopsis
```bash
php spp.php deploy:push [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployPushCommand.php
- `--artifact=` : Expects a value. Extracted via static analysis from DeployPushCommand.php
- `--dry-run` : Boolean flag. Extracted via static analysis from DeployPushCommand.php
- `--no-db` : Boolean flag. Extracted via static analysis from DeployPushCommand.php
- `--no-files` : Boolean flag. Extracted via static analysis from DeployPushCommand.php
- `--force` : Boolean flag. Extracted via static analysis from DeployPushCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDeploy\Scanner\ProjectScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \ZipArchive, \Exception, \SPPMod\SPPDB\SPPDB.


---

## `deploy:rollback`

**Description**: Roll back a remote target to a specific snapshot backup ID

### Synopsis
```bash
php spp.php deploy:rollback [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:rollback [target_uri] <backup_id> [--key=YOUR_API_KEY] [--force]

```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployRollbackCommand.php
- `--force` : Boolean flag. Extracted via static analysis from DeployRollbackCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `deploy:run`

**Description**: Securely execute an arbitrary shell command on the remote server

### Synopsis
```bash
php spp.php deploy:run [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:run [target_uri] \
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployRunCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `deploy:token:rotate`

**Description**: Rotate the secure deployment gateway token on both local and remote environments with zero downtime

### Synopsis
```bash
php spp.php deploy:token:rotate [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployTokenRotateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: deployment, token.


---

## `di:list`

**Description**: List the Dependency Injection container bindings

### Synopsis
```bash
php spp.php di:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from DiListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.


---

## `diff:apply`

**Description**: Apply a patch or delta file

### Synopsis
```bash
php spp.php diff:apply [OPTIONS]
```

### Extended Usage
```text
Usage: diff:apply --file=patch.json

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `diff:compare`

**Description**: Compare two JSON arrays or states

### Synopsis
```bash
php spp.php diff:compare [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php diff:compare --type=<ModelClass> --id=<ID> --rev=<RevID> [--json]

```

### Options
- `--type=` : Expects a value. Extracted via static analysis from DiffCompareCommand.php
- `--id=` : Expects a value. Extracted via static analysis from DiffCompareCommand.php
- `--rev=` : Expects a value. Extracted via static analysis from DiffCompareCommand.php
- `--json` : Boolean flag. Extracted via static analysis from DiffCompareCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `diff:history`

**Description**: View revision history of an entity

### Synopsis
```bash
php spp.php diff:history [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php diff:history --type=<ModelClass> --id=<ID> [--json]

```

### Options
- `--type=` : Expects a value. Extracted via static analysis from DiffHistoryCommand.php
- `--id=` : Expects a value. Extracted via static analysis from DiffHistoryCommand.php
- `--json` : Boolean flag. Extracted via static analysis from DiffHistoryCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `diff:rollback`

**Description**: Rollback an entity to a previous state

### Synopsis
```bash
php spp.php diff:rollback [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php diff:rollback --type=<ModelClass> --id=<ID> --rev=<RevID>

```

### Options
- `--type=` : Expects a value. Extracted via static analysis from DiffRollbackCommand.php
- `--id=` : Expects a value. Extracted via static analysis from DiffRollbackCommand.php
- `--rev=` : Expects a value. Extracted via static analysis from DiffRollbackCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `docs:api`

**Description**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:api [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `docs:build`

**Description**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:build [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `docs:man`

**Description**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:man [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `docs:openapi`

**Description**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:openapi [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `docs:phpdoc`

**Description**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:phpdoc [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `doctor`

**Description**: Diagnose the health of the WebOS architecture

### Synopsis
```bash
php spp.php doctor [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `drishyam:clear`

**Description**: Clear the Drishyam rendering cache

### Synopsis
```bash
php spp.php drishyam:clear [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `drishyam:compile`

**Description**: Pre-compile Drishyam templates for production

### Synopsis
```bash
php spp.php drishyam:compile [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from DrishyamCompileCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `drishyam:theme:check`

**Description**: Validate Drishyam theme assets and structure

### Synopsis
```bash
php spp.php drishyam:theme:check [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from DrishyamThemeCheckCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `ent:edit`

**Description**: Edit an existing SPPEntity definition

### Synopsis
```bash
php spp.php ent:edit [OPTIONS]
```

### Extended Usage
```text
Edits an existing SPPEntity definition. If run without flags, it will launch an interactive wizard.
Passing any of the double-dash flags will bypass the wizard and execute a non-interactive edit.

Usage:
  php spp.php ent:edit [EntityName] [OPTIONS]

Options:
  --table=TableName            Update the database table name.
  --extends=Class              Update the parent entity class (e.g. "\App\Entities\User").
  --login=true|false           Enable or disable SPP Login Support for this entity.
  --add-field="name:type"      Add or update attributes. Format: "name:type" (comma-separated).
  --remove-field="name"        Remove attributes by name (comma-separated).
  --add-relation="Target:..."  Add relationships. Format: "Target:Type:ForeignKey:PivotTable" (comma-separated).
  --remove-relation=index      Remove a relationship by its integer index.

Examples:
  Interactive Mode:
    php spp.php ent:edit Student

  Non-Interactive Edit:
    php spp.php ent:edit Student --table=new_students --add-field="graduation_year:int" --remove-field="age"
```

### Options
- `--table=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--extends=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--login=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--add-field=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--remove-field=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--add-relation=` : Expects a value. Extracted via static analysis from EntEditCommand.php
- `--remove-relation=` : Expects a value. Extracted via static analysis from EntEditCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `entity:crud`

**Description**: Manage SPP entities (list, create, edit, delete)

### Synopsis
```bash
php spp.php entity:crud [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `env:backup`

**Description**: Backup all environment configurations

### Synopsis
```bash
php spp.php env:backup [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \ZipArchive.


---

## `env:get`

**Description**: Get a specific configuration variable

### Synopsis
```bash
php spp.php env:get [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php env:get <key> [--app=appname]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvGetCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `env:list`

**Description**: List all environment and configuration variables for an app context

### Synopsis
```bash
php spp.php env:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.


---

## `env:mode`

**Description**: Switch environment error reporting mode between dev (Ignition errors) and prod (500 pages)

### Synopsis
```bash
php spp.php env:mode [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `env:set`

**Description**: Set a specific configuration variable

### Synopsis
```bash
php spp.php env:set [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php env:set <key> <value> [--app=appname]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvSetCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `env:status`

**Description**: Display system health and environment status

### Synopsis
```bash
php spp.php env:status [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvStatusCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `env:token:rotate`

**Description**: Rotate the system deployment token

### Synopsis
```bash
php spp.php env:token:rotate [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvTokenRotateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\SPPXDB\SPP_XDB.


---

## `event:dispatch`

**Description**: Alias for event:fire

### Synopsis
```bash
php spp.php event:dispatch [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php event:fire --event=<event_name> [--payload=<json>]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `event:fire`

**Description**: Trigger a specific event manually

### Synopsis
```bash
php spp.php event:fire [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php event:fire --event=<event_name> [--payload=<json>]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EventFireCommand.php
- `--event=` : Expects a value. Extracted via static analysis from EventFireCommand.php
- `--payload=` : Expects a value. Extracted via static analysis from EventFireCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `event:list-listeners`

**Description**: List all registered global event listeners

### Synopsis
```bash
php spp.php event:list-listeners [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EventListListenersCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.


---

## `ext:disable`

**Description**: Disable a specific extension

### Synopsis
```bash
php spp.php ext:disable [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ext:disable <extension_name>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `ext:enable`

**Description**: Enable a specific extension

### Synopsis
```bash
php spp.php ext:enable [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ext:enable <extension_name>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `ext:install`

**Description**: Install an extension from a zip or directory

### Synopsis
```bash
php spp.php ext:install [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ext:install --source=<path_or_url>

```

### Options
- `--source=` : Expects a value. Extracted via static analysis from ExtInstallCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `ext:list`

**Description**: List all available and installed extensions

### Synopsis
```bash
php spp.php ext:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `forge`

**Description**: Unified automation and LiveSync engine

### Synopsis
```bash
php spp.php forge [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \SPP\Core\ModuleCompiler, \SPP\Core\VersionManager, MakeUXComponentCommand, \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `form:crud`

**Description**: Manage SPP forms (list, create, edit, delete)

### Synopsis
```bash
php spp.php form:crud [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `frontend:debug`

**Description**: Toggle Frontend CDN development mode (on|off)

### Synopsis
```bash
php spp.php frontend:debug [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `generate`

**Description**: AI Copilot: Generate an entire application feature from a natural language prompt.

### Synopsis
```bash
php spp.php generate [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `group:create`

**Description**: Create a new shared resource group

### Synopsis
```bash
php spp.php group:create [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php group:create <group_name> [--extends=core] [--prefix=...]

```

### Options
- `--extends=` : Expects a value. Extracted via static analysis from GroupCreateCommand.php
- `--prefix=` : Expects a value. Extracted via static analysis from GroupCreateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: shared.


---

## `group:delete`

**Description**: Delete a shared resource group

### Synopsis
```bash
php spp.php group:delete [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php group:delete <group_name>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `group:edit`

**Description**: Edit an existing shared resource group

### Synopsis
```bash
php spp.php group:edit [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php group:edit <group_name> [--extends=...] [--prefix=...]

```

### Options
- `--extends=` : Expects a value. Extracted via static analysis from GroupEditCommand.php
- `--prefix=` : Expects a value. Extracted via static analysis from GroupEditCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `group:list`

**Description**: List all shared resource groups

### Synopsis
```bash
php spp.php group:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `i18n:export`

**Description**: Export translations for a specific locale to a JSON file.

### Synopsis
```bash
php spp.php i18n:export [OPTIONS]
```

### Options
- `--locale=` : Expects a value. Extracted via static analysis from I18nExportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `i18n:import`

**Description**: Import translations from a JSON file into the database.

### Synopsis
```bash
php spp.php i18n:import [OPTIONS]
```

### Options
- `--locale=` : Expects a value. Extracted via static analysis from I18nImportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `iam:abac`

**Description**: Manage Attribute-Based Access Control (ABAC) policies

### Synopsis
```bash
php spp.php iam:abac [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php iam:abac --action=create --param1=\
```

### Options
- `--json` : Boolean flag. Extracted via static analysis from ABACPolicyCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: SPPDB.


---

## `iam:roles`

**Description**: List all Roles and Entity Role Assignments

### Synopsis
```bash
php spp.php iam:roles [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php iam:roles list

```

### Options
- `--json` : Boolean flag. Extracted via static analysis from RoleCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: SPPDB.


---

## `import:component`

**Description**: Imports pristine air-gapped sovereign UI components

### Synopsis
```bash
php spp.php import:component [OPTIONS]
```

### Options
- `--target=` : Expects a value. Extracted via static analysis from ImportComponentCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `integration:install`

**Description**: Provision an external app directory and register the SPP route bypass

### Synopsis
```bash
php spp.php integration:install [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php integration:install <app_name> <route_path> [--isolation=virtual|physical]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \Exception.


---

## `integration:queue:work`

**Description**: Run the persistent CDC integration event queue worker

### Synopsis
```bash
php spp.php integration:queue:work [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: DBAdapter.


---

## `integration:restore`

**Description**: Time-travel a user state to a historical point using CQRS Event Sourcing

### Synopsis
```bash
php spp.php integration:restore [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php integration:restore <user_id> <timestamp_or_snapshot_id>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `integration:seed`

**Description**: Bulk seed local SPP users into a specific integration target

### Synopsis
```bash
php spp.php integration:seed [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php integration:seed <app_name>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: DBAdapter.


---

## `interdb:config`

**Description**: Get or set the interdb operating mode

### Synopsis
```bash
php spp.php interdb:config [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `interdb:mapping:add`

**Description**: Add a new InterDB mapping

### Synopsis
```bash
php spp.php interdb:mapping:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php interdb:mapping:add <alias> <engine> <table>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: InterDB.


---

## `interdb:mapping:list`

**Description**: List all InterDB mappings

### Synopsis
```bash
php spp.php interdb:mapping:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `interdb:mapping:remove`

**Description**: Remove an InterDB mapping

### Synopsis
```bash
php spp.php interdb:mapping:remove [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php interdb:mapping:remove <alias>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `issue`

**Description**: Manage SPPDocs issues directly from the command line (create, list, view, close)

### Synopsis
```bash
php spp.php issue [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `kernel:compile`

**Description**: Compiles the WebOS Kernel into the FastCGI performance cache.

### Synopsis
```bash
php spp.php kernel:compile [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `lang:export`

**Description**: Export active database translation overrides into JSON language file

### Synopsis
```bash
php spp.php lang:export [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangExportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `lang:import`

**Description**: Import JSON language file into active database translation overrides

### Synopsis
```bash
php spp.php lang:import [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangImportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `lang:list`

**Description**: List all translations

### Synopsis
```bash
php spp.php lang:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `lang:scan`

**Description**: Scan directories for new translation keys

### Synopsis
```bash
php spp.php lang:scan [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangScanCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: translation.


---

## `lang:set`

**Description**: Set a translation for a key

### Synopsis
```bash
php spp.php lang:set [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php lang:set <key> <locale> <translation>

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangSetCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `lekhak:generate-docs`

**Description**: Generates documentation nodes for SPP Core and Modules.

### Synopsis
```bash
php spp.php lekhak:generate-docs [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: DocGen, LekhakNode.


---

## `lekhak:setup`

**Description**: Initializes Lekhak CMS database tables.

### Synopsis
```bash
php spp.php lekhak:setup [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: SPPDB.


---

## `lint`

**Description**: Run SPP native linter on a file

### Synopsis
```bash
php spp.php lint [OPTIONS]
```

### Options
- `--file=` : Expects a value. Extracted via static analysis from LintCommand.php
- `--json` : Boolean flag. Extracted via static analysis from LintCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `list`

**Description**: Lists all discovered SPP CLI commands.

### Synopsis
```bash
php spp.php list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `live:status`

**Description**: Check the status of websocket/polling servers

### Synopsis
```bash
php spp.php live:status [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LiveStatusCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `live:trigger`

**Description**: Push a live event to clients

### Synopsis
```bash
php spp.php live:trigger [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php live:trigger --channel=<channel> --event=<event> [--payload=<json>]

```

### Options
- `--channel=` : Expects a value. Extracted via static analysis from LiveTriggerCommand.php
- `--event=` : Expects a value. Extracted via static analysis from LiveTriggerCommand.php
- `--payload=` : Expects a value. Extracted via static analysis from LiveTriggerCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `logger:clear`

**Description**: Clear the SPP application logs

### Synopsis
```bash
php spp.php logger:clear [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `logger:tail`

**Description**: Tail the SPP application log file

### Synopsis
```bash
php spp.php logger:tail [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `make:app`

**Description**: Create a new SPP application context

### Synopsis
```bash
php spp.php make:app [OPTIONS]
```

### Options
- `--enterprise` : Boolean flag. Extracted via static analysis from MakeAppCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: SPP.


---

## `make:app-legacy`

**Description**: Legacy scaffolder — forwards to make:app (kept for backward compatibility)

### Synopsis
```bash
php spp.php make:app-legacy [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: MakeAppCommand.


---

## `make:blade`

**Description**: Scaffold a new Blade template (Drishyam Paradigm)

### Synopsis
```bash
php spp.php make:blade [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:blade <ViewName>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: Drishyam, Blade.


---

## `make:blade-project`

**Description**: Scaffold a new Blade-enabled SPP application

### Synopsis
```bash
php spp.php make:blade-project [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:blade-project <app_name>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: Blade, SPP, app.


---

## `make:blade-scaffold`

**Description**: Create a full stack Blade scaffold (Entity, YAML Form, Controller, Blade Views)

### Synopsis
```bash
php spp.php make:blade-scaffold [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `make:command`

**Description**: Create a new CLI command class

### Synopsis
```bash
php spp.php make:command [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:command <name> [--app=appname] [--command=cmd:name]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: CLI.


---

## `make:command-test`

**Description**: Generate a boilerplate Parikshak feature test for a given command

### Synopsis
```bash
php spp.php make:command-test [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:command-test <CommandName> [--app=appname]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `make:controller`

**Description**: Create a new controller class

### Synopsis
```bash
php spp.php make:controller [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:controller <name> [--app=appname] [--resource]

```

### Options
- `--resource` : Boolean flag. Extracted via static analysis from MakeControllerCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: controller.


---

## `make:deployment`

**Description**: Generate Enterprise Docker and K8s scaffolding for the application.

### Synopsis
```bash
php spp.php make:deployment [OPTIONS]
```

### Options
- `--with-redis` : Boolean flag. Extracted via static analysis from MakeDeploymentCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `make:dotnet-service`

**Description**: Create a new .NET service project

### Synopsis
```bash
php spp.php make:dotnet-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:dotnet-service <name> [--app=context]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: console.


---

## `make:drupal-bridge`

**Description**: Scaffold a Drupal module to bridge SPP into Drupal

### Synopsis
```bash
php spp.php make:drupal-bridge [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: TwigFunction.


---

## `make:entity`

**Description**: Create a new SPPEntity definition

### Synopsis
```bash
php spp.php make:entity [OPTIONS]
```

### Extended Usage
```text
Creates a new SPPEntity definition. If run without flags, it will launch an interactive wizard.

Usage:
  php spp.php make:entity [EntityName] [OPTIONS]

Options:
  --app=AppName         Specify the application context (defaults to "default").
  --table=TableName     Specify the database table name (defaults to lowercase plural of EntityName).
  --extends=Class       Specify the parent entity class (e.g. "\App\Entities\User").
  --login=true|false    Enable or disable SPP Login Support for this entity.
  --fields="f1:type,f2" Define attributes. Format: "name:type". Default type is varchar(255).
  --relations="Rel"     Define relationships. Format: "Target:Type:ForeignKey:PivotTable".
                        Example: "\App\Entities\Course:ManyToMany:student_id:student_courses"
  --api, --resource     Generate a REST API controller for this entity.

Examples:
  Interactive Mode:
    php spp.php make:entity Student

  Non-Interactive Mode:
    php spp.php make:entity Student --table=spp_students --fields="name:varchar(255),age:int" --extends="\App\Entities\User" --login=true --relations="\App\Entities\Profile:OneToOne:student_id"
```

### Options
- `--fields=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--app=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--table=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--extends=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--login=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--relations=` : Expects a value. Extracted via static analysis from MakeEntityCommand.php
- `--api` : Boolean flag. Extracted via static analysis from MakeEntityCommand.php
- `--resource` : Boolean flag. Extracted via static analysis from MakeEntityCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: SPPEntity.


---

## `make:event`

**Description**: Create a new event entry and scaffold its handler

### Synopsis
```bash
php spp.php make:event [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:event <EventName> <HandlerClassName> [--app=appname] [--overridable] [--default-handler]

```

### Options
- `--overridable` : Boolean flag. Extracted via static analysis from MakeEventCommand.php
- `--default-handler` : Boolean flag. Extracted via static analysis from MakeEventCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: event.


---

## `make:eventhand`

**Description**: Create a new Event Handler class

### Synopsis
```bash
php spp.php make:eventhand [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:eventhand <HandlerClassName> [--app=appname]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes external system binaries or shell commands.
- Instantiates key components: Event.


---

## `make:form`

**Description**: Create a new SPP form definition

### Synopsis
```bash
php spp.php make:form [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:form <name> [--app=appname]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPP.


---

## `make:go-service`

**Description**: Create a new Go service script

### Synopsis
```bash
php spp.php make:go-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:go-service <name> [--app=context]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: Go.


---

## `make:java-service`

**Description**: Create a new Java service script

### Synopsis
```bash
php spp.php make:java-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:java-service <name> [--app=context]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: Java.


---

## `make:live-component`

**Description**: Create a new Live Component class

### Synopsis
```bash
php spp.php make:live-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:live-component <name> [--app=appname]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: Live.


---

## `make:middleware`

**Description**: Create a new middleware class

### Synopsis
```bash
php spp.php make:middleware [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:middleware <name> [--app=appname]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: middleware.


---

## `make:migration`

**Description**: Create a new database migration file

### Synopsis
```bash
php spp.php make:migration [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: database.


---

## `make:mixed-paradigm`

**Description**: Scaffold a Kitchen Sink view blending SPPView, Drishyam, and SPPUX

### Synopsis
```bash
php spp.php make:mixed-paradigm [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:mixed-paradigm <ViewName>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: Drishyam.


---

## `make:model`

**Description**: Create a new model class (Fluent-ready)

### Synopsis
```bash
php spp.php make:model [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:model <name> [--app=appname] [--table=tablename]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: model.


---

## `make:module`

**Description**: Create a new SPP module (System or App level)

### Synopsis
```bash
php spp.php make:module [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:module <name> [--scope=spp|optional|contrib|app] [--app=AppName]

```

### Options
- `--scope=` : Expects a value. Extracted via static analysis from MakeModuleCommand.php
- `--app=` : Expects a value. Extracted via static analysis from MakeModuleCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: SPP, MyService.


---

## `make:node-service`

**Description**: Create a new Node.js service script

### Synopsis
```bash
php spp.php make:node-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:node-service <name> [--app=context]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: Node.


---

## `make:partial`

**Description**: Scaffold a new external view partial template (HTML/PHP/JS)

### Synopsis
```bash
php spp.php make:partial [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:partial <PartialName.html|.php|.js> [--app=AppName]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: external.


---

## `make:perl-service`

**Description**: Create a new Perl service script

### Synopsis
```bash
php spp.php make:perl-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:perl-service <name> [--app=context]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: Perl.


---

## `make:python-service`

**Description**: Create a new Python service script

### Synopsis
```bash
php spp.php make:python-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:python-service <name> [--app=context]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: Python.


---

## `make:react-component`

**Description**: Scaffold a new React component (ESM/No-build)

### Synopsis
```bash
php spp.php make:react-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:react-component <ComponentName>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: React.


---

## `make:report`

**Description**: Scaffold a new SPPReport YAML configuration

### Synopsis
```bash
php spp.php make:report [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:report <name>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: SPPReport.


---

## `make:scaffold`

**Description**: Create a full stack scaffold (Entity, DB, Controller, View)

### Synopsis
```bash
php spp.php make:scaffold [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `make:seeder`

**Description**: Create a new Database Seeder class

### Synopsis
```bash
php spp.php make:seeder [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from MakeSeederCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: Database.


---

## `make:service`

**Description**: Create a new service class

### Synopsis
```bash
php spp.php make:service [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:service <name> [--app=appname] [--lang=python]

```

### Options
- `--lang=` : Expects a value. Extracted via static analysis from MakeServiceCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: service, MakePythonCommand, MakeNodeCommand, MakeGoCommand, MakeDotNetCommand.


---

## `make:sppview`

**Description**: Scaffold a new native AST SPPView template

### Synopsis
```bash
php spp.php make:sppview [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:sppview <ViewName>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: native.


---

## `make:stream`

**Description**: Scaffold a new external Turbo Stream template

### Synopsis
```bash
php spp.php make:stream [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:stream <StreamName.html|.php|.blade.php> [--app=AppName]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: external.


---

## `make:twig`

**Description**: Scaffold a new Twig template (Drishyam Paradigm)

### Synopsis
```bash
php spp.php make:twig [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:twig <ViewName>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: Drishyam, Twig.


---

## `make:ux-component`

**Description**: Scaffold a new SPP-UX reactive component

### Synopsis
```bash
php spp.php make:ux-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:ux-component <ComponentName> [--template=external]

```

### Options
- `--template=external` : Boolean flag. Extracted via static analysis from MakeUXComponentCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: reactive, SPP.


---

## `make:view`

**Description**: Create a new view definition (equivalent to Drupal Views).

### Synopsis
```bash
php spp.php make:view [OPTIONS]
```

### Options
- `--table=` : Expects a value. Extracted via static analysis from MakeViewCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: view, \SPPMod\SPPDB\SPPDB.


---

## `make:vue-component`

**Description**: Scaffold a new Vue 3 component (ESM/No-build)

### Synopsis
```bash
php spp.php make:vue-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:vue-component <ComponentName>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: Vue.


---

## `make:wizard`

**Description**: Scaffold a modern WizardController, workflow config, and partials

### Synopsis
```bash
php spp.php make:wizard [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:wizard <WizardName> [--app=AppName]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `man`

**Description**: Format and display manual pages for SPP commands

### Synopsis
```bash
php spp.php man [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: elements.


---

## `man:generate`

**Description**: Generate highly detailed man-pages in Markdown and UNIX roff formats

### Synopsis
```bash
php spp.php man:generate [OPTIONS]
```

### Options
- `--force` : Boolean flag. Extracted via static analysis from ManGenerateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \ReflectionClass.


---

## `manifest:export`

**Description**: Exports tool autodiscovery definitions for AI Copilots

### Synopsis
```bash
php spp.php manifest:export [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `mesh:add`

**Description**: Mounts a legacy application as a passthrough route in the WebOS Mesh

### Synopsis
```bash
php spp.php mesh:add [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `mesh:list`

**Description**: Lists all active Mesh passthrough routes

### Synopsis
```bash
php spp.php mesh:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `mesh:remove`

**Description**: Unmounts a legacy application from the WebOS Mesh

### Synopsis
```bash
php spp.php mesh:remove [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `mesh:update`

**Description**: Updates features for an existing mesh route

### Synopsis
```bash
php spp.php mesh:update [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `middleware:list`

**Description**: List the middleware pipeline for an app

### Synopsis
```bash
php spp.php middleware:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from MiddlewareListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.


---

## `migrate`

**Description**: Run pending database migrations

### Synopsis
```bash
php spp.php migrate [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPPMigrationManager.


---

## `migrate:make`

**Description**: Generate a new database migration class.

### Synopsis
```bash
php spp.php migrate:make [OPTIONS]
```

### Options
- `--name=` : Expects a value. Extracted via static analysis from MakeCommand.php
- `--app=` : Expects a value. Extracted via static analysis from MakeCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: database.


---

## `module:disable`

**Description**: Disable an SPP module

### Synopsis
```bash
php spp.php module:disable [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:disable <modulename>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `module:enable`

**Description**: Enable an SPP module

### Synopsis
```bash
php spp.php module:enable [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:enable <modulename>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `module:install`

**Description**: Install or upgrade a specific module or all active modules

### Synopsis
```bash
php spp.php module:install [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:install <modulename> [--all]

```

### Options
- `--all` : Boolean flag. Extracted via static analysis from ModuleInstallCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `module:list`

**Description**: Discovers and tabulates active kernel framework modules

### Synopsis
```bash
php spp.php module:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `module:setting:list`

**Description**: List all settings for a given module

### Synopsis
```bash
php spp.php module:setting:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `module:setting:update`

**Description**: Update a configuration setting for a specific module

### Synopsis
```bash
php spp.php module:setting:update [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `module:uninstall`

**Description**: Uninstall a module (drops tracking but retains data tables)

### Synopsis
```bash
php spp.php module:uninstall [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:uninstall <modulename>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `module:update`

**Description**: Execute the update hook for a specific module

### Synopsis
```bash
php spp.php module:update [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:update <modulename> [--from=1.0] [--to=1.1]

```

### Options
- `--from=` : Expects a value. Extracted via static analysis from ModuleUpdateCommand.php
- `--to=` : Expects a value. Extracted via static analysis from ModuleUpdateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `oauth:client:create`

**Description**: Create a new OAuth 2.0 Client App

### Synopsis
```bash
php spp.php oauth:client:create [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php oauth:client:create --name=\
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: OAuth, SPPDB.


---

## `oauth:client:delete`

**Description**: Delete an OAuth 2.0 Client App

### Synopsis
```bash
php spp.php oauth:client:delete [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php oauth:client:delete <id>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: SPPDB.


---

## `oauth:client:list`

**Description**: List all OAuth 2.0 Client Apps

### Synopsis
```bash
php spp.php oauth:client:list [OPTIONS]
```

### Options
- `--json` : Boolean flag. Extracted via static analysis from OAuthClientListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: SPPDB.


---

## `optimize:ux`

**Description**: AOT Pre-compile SPP-UX tagged templates to eliminate browser JIT parsing overhead

### Synopsis
```bash
php spp.php optimize:ux [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: DOMDocument, DOMXPath, \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `pkg:install`

**Description**: Download and install a native SPP app or external package from a URL, local path, or central registry

### Synopsis
```bash
php spp.php pkg:install [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php pkg:install <source_or_name> <app_name> [--type=native|external] [--route=/path]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \ZipArchive.


---

## `profile:report:generate`

**Description**: Dump a performance profile trace for debugging

### Synopsis
```bash
php spp.php profile:report:generate [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `profile:status`

**Description**: Check if the performance profiler is running/enabled

### Synopsis
```bash
php spp.php profile:status [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `queue:list`

**Description**: List all jobs currently in the queue

### Synopsis
```bash
php spp.php queue:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from QueueListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `queue:work`

**Description**: Starts a worker loop to process background jobs from the queue.

### Synopsis
```bash
php spp.php queue:work [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php queue:work

```

### Options
- `--help` : Boolean flag. Extracted via static analysis from QueueWorkCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `role:create`

**Description**: Create a new role

### Synopsis
```bash
php spp.php role:create [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: role, \SPPMod\SPPDB\SPPDB.


---

## `role:list`

**Description**: List all defined roles and permissions

### Synopsis
```bash
php spp.php role:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `schedule:run`

**Description**: Run all scheduled cron tasks declared by active modules

### Synopsis
```bash
php spp.php schedule:run [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \SPP\Cron\Scheduler.


---

## `scim:test:user`

**Description**: Test SCIM User Provisioning locally

### Synopsis
```bash
php spp.php scim:test:user [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php scim:test:user <username> [email]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SCIMHandler, \ReflectionClass.


---

## `serve`

**Description**: Start a local development server for the current application

### Synopsis
```bash
php spp.php serve [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php serve [--port=8000]

```

### Options
- `--help` : Boolean flag. Extracted via static analysis from AppServeCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes external system binaries or shell commands.


---

## `serve:async`

**Description**: Boot the persistent memory asynchronous coroutine runtime (FrankenPHP/OpenSwoole)

### Synopsis
```bash
php spp.php serve:async [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php serve:async [--app=name] [--port=8080]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from AppServeAsyncCommand.php
- `--port=` : Expects a value. Extracted via static analysis from AppServeAsyncCommand.php
- `--help` : Boolean flag. Extracted via static analysis from AppServeAsyncCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `service:crud`

**Description**: Manage SPP services (list, create, edit, delete)

### Synopsis
```bash
php spp.php service:crud [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `session:clean`

**Description**: Clean up expired sessions

### Synopsis
```bash
php spp.php session:clean [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `session:destroy-all`

**Description**: Invalidate all active sessions across the application

### Synopsis
```bash
php spp.php session:destroy-all [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `shell`

**Description**: Launch the interactive SPP Shell Mode (run all CLI commands, switch apps, inspect state, tabs, AI, polyglot, etc.).

### Synopsis
```bash
php spp.php shell [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \SPP\Core\InteractiveShell.


---

## `site:install`

**Description**: Initialize the database and load default configurations for a specific profile.

### Synopsis
```bash
php spp.php site:install [OPTIONS]
```

### Options
- `--profile=` : Expects a value. Extracted via static analysis from SiteInstallCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `sppdocs:audit:verify`

**Description**: Verify the cryptographic SHA-256 hash chain integrity of an SPPDocs project audit trail

### Synopsis
```bash
php spp.php sppdocs:audit:verify [OPTIONS]
```

### Options
- `--project=` : Expects a value. Extracted via static analysis from SPPDocsAuditVerifyCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `sppdocs:storage:migrate`

**Description**: Migrate SPPDocs project issues between Flat-File JSON and SQLite storage

### Synopsis
```bash
php spp.php sppdocs:storage:migrate [OPTIONS]
```

### Options
- `--project=` : Expects a value. Extracted via static analysis from SPPDocsStorageMigrateCommand.php
- `--to=` : Expects a value. Extracted via static analysis from SPPDocsStorageMigrateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: JsonStorageDriver, SqliteStorageDriver.


---

## `storage:clean`

**Description**: Clean up temporary files in storage

### Synopsis
```bash
php spp.php storage:clean [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from StorageCleanCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `storage:link`

**Description**: Create symbolic links for public storage

### Synopsis
```bash
php spp.php storage:link [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from StorageLinkCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `storage:sync`

**Description**: Sync local storage with external disks (stub)

### Synopsis
```bash
php spp.php storage:sync [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from StorageSyncCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `sys:debug`

**Description**: Toggle global framework debug mode (on|off)

### Synopsis
```bash
php spp.php sys:debug [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php sys:debug on|off

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `sys:seed`

**Description**: Run all database seeders for an application

### Synopsis
```bash
php spp.php sys:seed [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPPDB.


---

## `sys:status`

**Description**: Displays framework health, environment diagnostics, and polyglot bridge status

### Synopsis
```bash
php spp.php sys:status [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `sys:test:auto`

**Description**: Runs Automated Evolutionary Testing (Parikshak) for the current application.

### Synopsis
```bash
php spp.php sys:test:auto [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB, Parikshak.


---

## `sys:upgrade`

**Description**: Synchronize the database schema incrementally from all active module definitions (db.yml)

### Synopsis
```bash
php spp.php sys:upgrade [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPPDB.


---

## `test`

**Description**: Run Parikshak Unit and Feature Tests

### Synopsis
```bash
php spp.php test [OPTIONS]
```

### Options
- `--coverage` : Boolean flag. Extracted via static analysis from TestCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB, SPPTestRunner.


---

## `test:blueprint`

**Description**: Generate a structural blueprint for an entity

### Synopsis
```bash
php spp.php test:blueprint [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:blueprint <EntityClass>

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from TestBlueprintCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: parikshak.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\Parikshak\Parikshak.


---

## `test:dry-run`

**Description**: Dry-run all registered commands to catch syntax and initialization errors

### Synopsis
```bash
php spp.php test:dry-run [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes external system binaries or shell commands.


---

## `test:module`

**Description**: Run PHPUnit tests for an isolated module

### Synopsis
```bash
php spp.php test:module [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:module <modulename>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes external system binaries or shell commands.


---

## `test:monkey`

**Description**: Runs chaos monkey / fuzzing scenarios for an entity

### Synopsis
```bash
php spp.php test:monkey [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:monkey <EntityClass>

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from TestMonkeyCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: parikshak.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\Parikshak\Parikshak.


---

## `test:routes`

**Description**: Test route scanner

### Synopsis
```bash
php spp.php test:routes [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from TestRouteCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `test:run`

**Description**: Runs Parikshak evaluation for an entity or the whole suite

### Synopsis
```bash
php spp.php test:run [OPTIONS]
```

### Options
- `--coverage` : Boolean flag. Extracted via static analysis from TestRunCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: parikshak.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\Parikshak\Parikshak.


---

## `theme:activate`

**Description**: Switch the active theme adapter (native/wp/joomla) and optionally set the theme name

### Synopsis
```bash
php spp.php theme:activate [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `tinker`

**Description**: Interact with your application in a REPL shell.

### Synopsis
```bash
php spp.php tinker [OPTIONS]
```

### Options
- `--force` : Boolean flag. Extracted via static analysis from TinkerCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `ui:add`

**Description**: Add beautifully styled, zero-build SPP-UX components to your project

### Synopsis
```bash
php spp.php ui:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ui:add <component_name>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `user:create`

**Description**: Create a new user account

### Synopsis
```bash
php spp.php user:create [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: user, \SPPMod\SPPDB\SPPDB.


---

## `user:delete`

**Description**: Delete a user account by username or ID

### Synopsis
```bash
php spp.php user:delete [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `user:list`

**Description**: List all registered framework users

### Synopsis
```bash
php spp.php user:list [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `user:password:reset`

**Description**: Reset password for a user

### Synopsis
```bash
php spp.php user:password:reset [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: password, \SPPMod\SPPDB\SPPDB.


---

## `userprofile:export`

**Description**: Export user profile data for compliance/GDPR

### Synopsis
```bash
php spp.php userprofile:export [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php userprofile:export --user=<user_id>

```

### Options
- `--user=` : Expects a value. Extracted via static analysis from UserProfileExportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `userprofile:schema:update`

**Description**: Sync extended user profile metadata schemas

### Synopsis
```bash
php spp.php userprofile:schema:update [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `ux:debug`

**Description**: Toggle SPP-UX verbose logging (on|off)

### Synopsis
```bash
php spp.php ux:debug [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).


---

## `verify:sovereignty`

**Description**: Validates complete stack self-containment/zero external links

### Synopsis
```bash
php spp.php verify:sovereignty [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `view:cache`

**Description**: Pre-compiles all AST views into PHP for optimal performance

### Synopsis
```bash
php spp.php view:cache [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:cache [--app=name]

```

### Options
- `--help` : Boolean flag. Extracted via static analysis from ViewCacheCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `view:page:add`

**Description**: Add a new page route to an app

### Synopsis
```bash
php spp.php view:page:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:page:add --name=<route> --url=<target> [--app=default] [--source=yaml|db]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php
- `--name=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php
- `--url=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php
- `--source=` : Expects a value. Extracted via static analysis from ViewPageAddCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: page.


---

## `view:page:list`

**Description**: List all registered pages/routes for an app

### Synopsis
```bash
php spp.php view:page:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewPageListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `view:page:remove`

**Description**: Remove a page route from an app

### Synopsis
```bash
php spp.php view:page:remove [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:page:remove --name=<route> [--app=default] [--source=yaml|db]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewPageRemoveCommand.php
- `--name=` : Expects a value. Extracted via static analysis from ViewPageRemoveCommand.php
- `--source=` : Expects a value. Extracted via static analysis from ViewPageRemoveCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `view:service:add`

**Description**: Register a new AJAX service endpoint

### Synopsis
```bash
php spp.php view:service:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:add --name=<service> --script=<path> [--method=POST] [--app=default] [--source=yaml|db]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php
- `--script=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php
- `--method=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php
- `--source=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: AJAX.


---

## `view:service:list`

**Description**: List all registered AJAX services for an app

### Synopsis
```bash
php spp.php view:service:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `view:service:remove`

**Description**: Remove an AJAX service endpoint from an app

### Synopsis
```bash
php spp.php view:service:remove [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:remove --name=<service> [--app=default] [--source=yaml|db]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceRemoveCommand.php
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceRemoveCommand.php
- `--source=` : Expects a value. Extracted via static analysis from ViewServiceRemoveCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `view:service:test`

**Description**: Test an AJAX service endpoint from the CLI

### Synopsis
```bash
php spp.php view:service:test [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:test --name=<service> [--app=default] [--payload=
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php
- `--payload=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).


---

## `workflow:dump`

**Description**: Dump a workflow definition as a visual state graph (Mermaid.js or Graphviz DOT)

### Synopsis
```bash
php spp.php workflow:dump [OPTIONS]
```

### Options
- `--format=` : Expects a value. Extracted via static analysis from WorkflowDumpCommand.php
- `--file=` : Expects a value. Extracted via static analysis from WorkflowDumpCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

## `workflow:process-timeouts`

**Description**: Process SLA timeouts on entities and trigger automatic escalation transitions

### Synopsis
```bash
php spp.php workflow:process-timeouts [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB.


---

## `xdb:describe`

**Description**: Describe the schema of an XDB table

### Synopsis
```bash
php spp.php xdb:describe [OPTIONS]
```

### Extended Usage
```text
Usage: php spp xdb:describe <table_name> [--db=dbname]

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \SPPMod\SPPXDB\SPP_XDB.


---

## `xdb:list-dbs`

**Description**: List all available XDB databases

### Synopsis
```bash
php spp.php xdb:list-dbs [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \SPPMod\SPPXDB\SPP_XDB.


---

## `xdb:list-tables`

**Description**: List all tables in an XDB database

### Synopsis
```bash
php spp.php xdb:list-tables [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \SPPMod\SPPXDB\SPP_XDB.


---

## `xdb:make:migration`

**Description**: Create a new SPP_XDB migration file

### Synopsis
```bash
php spp.php xdb:make:migration [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php xdb:make:migration <name_of_table>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPP_XDB, MigrationManager.


---

## `xdb:make:seeder`

**Description**: Create a new SPP_XDB seeder file

### Synopsis
```bash
php spp.php xdb:make:seeder [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php xdb:make:seeder <name_of_seeder>

```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPP_XDB, SeederManager.


---

## `xdb:migrate`

**Description**: Run SPP_XDB Database Migrations

### Synopsis
```bash
php spp.php xdb:migrate [OPTIONS]
```

### Options
- `--steps=` : Expects a value. Extracted via static analysis from XdbMigrateCommand.php
- `--rollback` : Boolean flag. Extracted via static analysis from XdbMigrateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPP_XDB, MigrationManager.


---

## `xdb:query`

**Description**: Execute a SQL or XPath query on the XML database

### Synopsis
```bash
php spp.php xdb:query [OPTIONS]
```

### Extended Usage
```text
Usage: php spp xdb:query \
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: \SPPMod\SPPXDB\SPP_XDB.


---

## `xdb:seed`

**Description**: Run SPP_XDB Database Seeders

### Synopsis
```bash
php spp.php xdb:seed [OPTIONS]
```

### Options
- `--class=` : Expects a value. Extracted via static analysis from XdbSeedCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPP_XDB, SeederManager.


---

## `xdb:shell`

**Description**: Launch the interactive SPPXDB shell

### Synopsis
```bash
php spp.php xdb:shell [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.


---

