# SPP CLI Manual

Detailed reference for all `spp.php` commands.

## Table of Contents
- [``](#)
- [`admin:adminrbac`](#adminadminrbac)
- [`admin:ai`](#adminai)
- [`admin:audit`](#adminaudit)
- [`admin:auth`](#adminauth)
- [`admin:bootstrap`](#adminbootstrap)
- [`admin:config`](#adminconfig)
- [`admin:core`](#admincore)
- [`admin:diagnostics`](#admindiagnostics)
- [`admin:docs`](#admindocs)
- [`admin:entities`](#adminentities)
- [`admin:forms`](#adminforms)
- [`admin:general`](#admingeneral)
- [`admin:iam`](#adminiam)
- [`admin:legacy`](#adminlegacy)
- [`admin:lifecycle`](#adminlifecycle)
- [`admin:modules`](#adminmodules)
- [`admin:routing`](#adminrouting)
- [`admin:spplang`](#adminspplang)
- [`admin:xdb`](#adminxdb)
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
- [`dev:adminrbac`](#devadminrbac)
- [`dev:ai`](#devai)
- [`dev:audit`](#devaudit)
- [`dev:auth`](#devauth)
- [`dev:codeeditor`](#devcodeeditor)
- [`dev:config`](#devconfig)
- [`dev:core`](#devcore)
- [`dev:diagnostics`](#devdiagnostics)
- [`dev:docs`](#devdocs)
- [`dev:entities`](#deventities)
- [`dev:forms`](#devforms)
- [`dev:general`](#devgeneral)
- [`dev:iam`](#deviam)
- [`dev:legacy`](#devlegacy)
- [`dev:lifecycle`](#devlifecycle)
- [`dev:modules`](#devmodules)
- [`dev:routing`](#devrouting)
- [`dev:spplang`](#devspplang)
- [`dev:xdb`](#devxdb)
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
- [`make:polyglot`](#makepolyglot)
- [`make:polyglot-partial`](#makepolyglotpartial)
- [`make:python-service`](#makepythonservice)
- [`make:react-component`](#makereactcomponent)
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
- [`serve:async`](#serveasync)
- [`service:crud`](#servicecrud)
- [`session:clean`](#sessionclean)
- [`session:destroy-all`](#sessiondestroyall)
- [`shell`](#shell)
- [`site:install`](#siteinstall)
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

## ``

**Purpose**: Test SCIM User Provisioning locally

### Synopsis
```bash
php spp.php  [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php scim:test:user <username> [email]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SCIMHandler, \ReflectionClass.


---

## `admin:adminrbac`

**Purpose**: Manage Admin AdminRBAC operations. Usage: admin:adminrbac <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:adminrbac [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB.


---

## `admin:ai`

**Purpose**: Manage Admin AI operations. Usage: admin:ai <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:ai [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: sppai.


---

## `admin:audit`

**Purpose**: Manage Admin Audit operations. Usage: admin:audit <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:audit [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `admin:auth`

**Purpose**: Manage Admin Auth operations. Usage: admin:auth <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:auth [OPTIONS]
```

### Options Available
- `--spp_admin_fallback` : Boolean flag or option. Extracted via static analysis.
- `--spp_admin_user` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPAuth\SPPUser, \SPPMod\SPPDB\SPPDB.


---

## `admin:bootstrap`

**Purpose**: Initialize SPP Admin environment (XDB Provisioning)

### Synopsis
```bash
php spp.php admin:bootstrap [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPDB.


---

## `admin:config`

**Purpose**: Manage Admin Config operations. Usage: admin:config <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:config [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `admin:core`

**Purpose**: Manage Admin Core operations. Usage: admin:core <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:core [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, \SPP\EventParams.


---

## `admin:diagnostics`

**Purpose**: Manage Admin Diagnostics operations. Usage: admin:diagnostics <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:diagnostics [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.
- Interacts with the application cache layer (Redis/Memcached).


---

## `admin:docs`

**Purpose**: Manage Admin Docs operations. Usage: admin:docs <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:docs [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `admin:entities`

**Purpose**: Manage Admin Entities operations. Usage: admin:entities <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:entities [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Bootstraps a full application execution context via Scheduler.


---

## `admin:forms`

**Purpose**: Manage Admin Forms operations. Usage: admin:forms <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:forms [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Bootstraps a full application execution context via Scheduler.


---

## `admin:general`

**Purpose**: Manage Admin General operations. Usage: admin:general <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:general [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPPMod\SPPAPI\LiveAction.


---

## `admin:iam`

**Purpose**: Manage Admin IAM operations. Usage: admin:iam <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:iam [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, \SPPMod\SPPAuth\SPPGroup, secret.


---

## `admin:legacy`

**Purpose**: Manage Admin Legacy operations. Usage: admin:legacy <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:legacy [OPTIONS]
```

### Options Available
- `--apps` : Boolean flag or option. Extracted via static analysis.
- `--enable_api` : Boolean flag or option. Extracted via static analysis.
- `--columns` : Boolean flag or option. Extracted via static analysis.
- `--fields` : Boolean flag or option. Extracted via static analysis.
- `--modules` : Boolean flag or option. Extracted via static analysis.
- `--name` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \\Exception, record, \SPPMod\SPPDB\SPPDB, RecursiveIteratorIterator, RecursiveDirectoryIterator, \ReflectionClass, \SPP\Module.
- Makes outbound HTTP requests to external APIs or services.


---

## `admin:lifecycle`

**Purpose**: Manage Admin Lifecycle operations. Usage: admin:lifecycle <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:lifecycle [OPTIONS]
```

### Options Available
- `--environments` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPP\Module.
- Makes outbound HTTP requests to external APIs or services.


---

## `admin:modules`

**Purpose**: Manage Admin Modules operations. Usage: admin:modules <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:modules [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPP\Module.


---

## `admin:routing`

**Purpose**: Manage Admin Routing operations. Usage: admin:routing <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:routing [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `admin:spplang`

**Purpose**: Manage Admin spplang operations. Usage: admin:spplang <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:spplang [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: translation.


---

## `admin:xdb`

**Purpose**: Manage Admin XDB operations. Usage: admin:xdb <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:xdb [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB, \SPPMod\SPPXDB\MigrationManager, \SPPMod\SPPXDB\SeederManager.


---

## `ai:benchmark:models`

**Purpose**: Benchmark configured AI models (Ollama, OpenAI, Anthropic) for tool calling latency and schema accuracy

### Synopsis
```bash
php spp.php ai:benchmark:models [OPTIONS]
```

### Options Available
- `--provider=` : Expects a value. Extracted via static analysis.
- `--models=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `ai:make:workflow`

**Purpose**: Synthesize natural language business requirements into valid sppworkflow YAML definitions

### Synopsis
```bash
php spp.php ai:make:workflow [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ai:make:workflow <workflow_name> \
```

### Options Available
- `--provider=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Dynamically loads SPP kernel modules: sppai.
- Bootstraps a full application execution context via Scheduler.


---

## `ai:prompt`

**Purpose**: Send a prompt to the AI provider

### Synopsis
```bash
php spp.php ai:prompt [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ai:prompt \
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.
- `--provider=` : Expects a value. Extracted via static analysis.
- `--model=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: sppai.
- Bootstraps a full application execution context via Scheduler.


---

## `ai:providers`

**Purpose**: List all registered AI providers

### Synopsis
```bash
php spp.php ai:providers [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: sppai.
- Bootstraps a full application execution context via Scheduler.


---

## `ai:refactor:enterprise`

**Purpose**: AI-powered automated refactoring daemon to modernize legacy code into strict SPP enterprise compliance

### Synopsis
```bash
php spp.php ai:refactor:enterprise [OPTIONS]
```

### Options Available
- `--path=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `api:key:generate`

**Purpose**: Generates a new permanent API Key.

### Synopsis
```bash
php spp.php api:key:generate [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: permanent, \SPPMod\SPPDB\SPPDB.


---

## `api:key:revoke`

**Purpose**: Revoke an existing API token

### Synopsis
```bash
php spp.php api:key:revoke [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php api:key:revoke --token=<token>

```

### Options Available
- `--token=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `api:route:list`

**Purpose**: Tabulate all exposed REST API routes

### Synopsis
```bash
php spp.php api:route:list [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `app:config`

**Purpose**: Configure application settings (e.g., base_url, table_prefix)

### Synopsis
```bash
php spp.php app:config [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php app:config <app_name> [--base_url=...] [--table_prefix=...]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `app:default`

**Purpose**: Set or view the default global CLI application context

### Synopsis
```bash
php spp.php app:default [OPTIONS]
```

### Options Available
- `--set=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `app:list`

**Purpose**: List all registered SPP applications

### Synopsis
```bash
php spp.php app:list [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `app:quota`

**Purpose**: Set hardware resource limits for a Guest App in the WebOS Registry. Usage: app:quota <alias> [--ram=...] [--cpu=...]

### Synopsis
```bash
php spp.php app:quota [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php app:quota <alias> [--ram=...] [--cpu=...]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `app:set-base`

**Purpose**: Set an application as the primary/base application

### Synopsis
```bash
php spp.php app:set-base [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php app:set-base <app_name>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `ask`

**Purpose**: Ask the SPP AI Mentor a question about the framework.

### Synopsis
```bash
php spp.php ask [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ask \
```

### Options Available
- `--error` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `audit:lineage`

**Purpose**: Traverses and verifies cryptographic Merkle-DAG trace logs

### Synopsis
```bash
php spp.php audit:lineage [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `auth:tokens`

**Purpose**: Manage Personal Access Tokens for API Authentication

### Synopsis
```bash
php spp.php auth:tokens [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPUser, SPPDB.


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

## `cache:clear`

**Purpose**: Clear the application file/redis cache

### Synopsis
```bash
php spp.php cache:clear [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Interacts with the application cache layer (Redis/Memcached).


---

## `cache:compile-registry`

**Purpose**: Rebuilds the Orion Cache and System Registry

### Synopsis
```bash
php spp.php cache:compile-registry [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPP\EventParams.


---

## `cache:prune`

**Purpose**: Prune expired cache items from storage

### Synopsis
```bash
php spp.php cache:prune [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Interacts with the application cache layer (Redis/Memcached).


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
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \RecursiveIteratorIterator, \RecursiveDirectoryIterator.
- Interacts with the application cache layer (Redis/Memcached).


---

## `clear:aicache`

**Purpose**: Clears the WebOS AI Decision cache.

### Synopsis
```bash
php spp.php clear:aicache [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `clear:cache`

**Purpose**: Clear the application file/redis cache

### Synopsis
```bash
php spp.php clear:cache [OPTIONS]
```

### Options Available
No static options detected for this command.

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
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPP\CLI\Commands\WorkflowProcessTimeoutsCommand.


---

## `db:migration:verify-zero-downtime`

**Purpose**: Perform a dry-run analysis of database migration DDL statements to verify zero-downtime compliance and schema safety

### Synopsis
```bash
php spp.php db:migration:verify-zero-downtime [OPTIONS]
```

### Options Available
- `--path=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `db:sync`

**Purpose**: Synchronize data between two database adapters (e.g. MySQL to XDB)

### Synopsis
```bash
php spp.php db:sync [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php db:sync --from=[engine:table] --to=[engine:table]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPPDB.


---

## `db:verify`

**Purpose**: Runs the SPP XDB MySQL Compatibility Verification Suite

### Synopsis
```bash
php spp.php db:verify [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.


---

## `dbsettings:export`

**Purpose**: Export SPP module DB settings to JSON

### Synopsis
```bash
php spp.php dbsettings:export [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `dbsettings:import`

**Purpose**: Import SPP module DB settings from JSON

### Synopsis
```bash
php spp.php dbsettings:import [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php dbsettings:import --file=settings.json [--app=<app_name>]

```

### Options Available
- `--file=` : Expects a value. Extracted via static analysis.
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `delete:app`

**Purpose**: Delete an SPP application context and all its data (files, config, caches, views)

### Synopsis
```bash
php spp.php delete:app [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php delete:app <AppName> [--force] [--keep-db] [--dry-run]

```

### Options Available
- `--force` : Boolean flag or option. Extracted via static analysis.
- `--keep-db` : Boolean flag or option. Extracted via static analysis.
- `--dry-run` : Boolean flag or option. Extracted via static analysis.
- `----force` : Boolean flag or option. Extracted via static analysis.
- `----keep-db` : Boolean flag or option. Extracted via static analysis.
- `----dry-run` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \PDO, \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `deploy:backups`

**Purpose**: List available snapshot backups on a remote target for rollback

### Synopsis
```bash
php spp.php deploy:backups [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `deploy:build`

**Purpose**: Create a local deployment artifact bundle without pushing

### Synopsis
```bash
php spp.php deploy:build [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:build <target_uri> [--key=YOUR_API_KEY] [--no-db] [--no-files]

```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--no-db` : Boolean flag or option. Extracted via static analysis.
- `--no-files` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--sql` : Boolean flag or option. Extracted via static analysis.
- `--Create Table` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDeploy\Scanner\ProjectScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \ZipArchive, \SPPMod\SPPDB\SPPDB.


---

## `deploy:cleanup`

**Purpose**: Prune old rollback snapshots from the remote target server

### Synopsis
```bash
php spp.php deploy:cleanup [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--keep=` : Expects a value. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `deploy:cluster`

**Purpose**: Deploy to a multi-server cluster sequentially

### Synopsis
```bash
php spp.php deploy:cluster [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:cluster <cluster_name>

```

### Options Available
- `--force` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: DeployPushCommand.


---

## `deploy:env`

**Purpose**: Manage remote environment variables securely

### Synopsis
```bash
php spp.php deploy:env [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:env [target_uri] push --key=MY_KEY --value=MY_VALUE [--key_api=YOUR_API_KEY]

```

### Options Available
- `--key_api=` : Expects a value. Extracted via static analysis.
- `--key=` : Expects a value. Extracted via static analysis.
- `--value=` : Expects a value. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `deploy:history`

**Purpose**: 

### Synopsis
```bash
php spp.php deploy:history [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `deploy:init`

**Purpose**: 

### Synopsis
```bash
php spp.php deploy:init [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `deploy:logs`

**Purpose**: View and tail remote application error logs securely over HTTP

### Synopsis
```bash
php spp.php deploy:logs [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--lines=` : Expects a value. Extracted via static analysis.
- `--tail` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `deploy:maintenance`

**Purpose**: Toggle manual maintenance mode on a remote target or local environment

### Synopsis
```bash
php spp.php deploy:maintenance [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--on` : Boolean flag or option. Extracted via static analysis.
- `--off` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `deploy:plan`

**Purpose**: Perform a dry run to view file changes and raw database SQL diffs before deploying

### Synopsis
```bash
php spp.php deploy:plan [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:plan <target_uri> [--key=YOUR_API_KEY] [--no-db]

```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--no-db` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--sql` : Boolean flag or option. Extracted via static analysis.
- `--Create Table` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDeploy\Scanner\FileScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \SPPMod\SPPDB\SPPDB.


---

## `deploy:pull`

**Purpose**: 

### Synopsis
```bash
php spp.php deploy:pull [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--force` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--debug` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \ZipArchive, \SPPMod\SPPDB\SPPDB.


---

## `deploy:push`

**Purpose**: Push the local project state to a remote SPP target server

### Synopsis
```bash
php spp.php deploy:push [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--artifact=` : Expects a value. Extracted via static analysis.
- `--dry-run` : Boolean flag or option. Extracted via static analysis.
- `--no-db` : Boolean flag or option. Extracted via static analysis.
- `--no-files` : Boolean flag or option. Extracted via static analysis.
- `--force` : Boolean flag or option. Extracted via static analysis.
- `--pre_deploy` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--message` : Boolean flag or option. Extracted via static analysis.
- `--keys` : Boolean flag or option. Extracted via static analysis.
- `--debug` : Boolean flag or option. Extracted via static analysis.
- `--sql` : Boolean flag or option. Extracted via static analysis.
- `--Create Table` : Boolean flag or option. Extracted via static analysis.
- `--webhooks` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDeploy\Scanner\ProjectScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \ZipArchive, \SPPMod\SPPDB\SPPDB.


---

## `deploy:rollback`

**Purpose**: Roll back a remote target to a specific snapshot backup ID

### Synopsis
```bash
php spp.php deploy:rollback [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:rollback [target_uri] <backup_id> [--key=YOUR_API_KEY] [--force]

```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--force` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `deploy:run`

**Purpose**: Securely execute an arbitrary shell command on the remote server

### Synopsis
```bash
php spp.php deploy:run [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:run [target_uri] \
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--exit_code` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `deploy:token:rotate`

**Purpose**: Rotate the secure deployment gateway token on both local and remote environments with zero downtime

### Synopsis
```bash
php spp.php deploy:token:rotate [OPTIONS]
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: deployment, token.


---

## `dev:adminrbac`

**Purpose**: Manage Dev AdminRBAC operations. Usage: dev:adminrbac <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:adminrbac [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB.


---

## `dev:ai`

**Purpose**: Manage Dev AI operations. Usage: admin:ai <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:ai [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: sppai.


---

## `dev:audit`

**Purpose**: Manage Dev Audit operations. Usage: admin:audit <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:audit [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `dev:auth`

**Purpose**: Manage Dev Auth operations. Usage: admin:auth <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:auth [OPTIONS]
```

### Options Available
- `--spp_dev_fallback` : Boolean flag or option. Extracted via static analysis.
- `--spp_dev_user` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPAuth\SPPUser, \SPPMod\SPPDB\SPPDB.


---

## `dev:codeeditor`

**Purpose**: Manage Dev CodeEditor operations. Usage: dev:codeeditor <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:codeeditor [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `dev:config`

**Purpose**: Manage Dev Config operations. Usage: admin:config <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:config [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `dev:core`

**Purpose**: Manage Dev Core operations. Usage: admin:core <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:core [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, \SPP\EventParams.


---

## `dev:diagnostics`

**Purpose**: Manage Dev Diagnostics operations. Usage: admin:diagnostics <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:diagnostics [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.
- Interacts with the application cache layer (Redis/Memcached).


---

## `dev:docs`

**Purpose**: Manage Dev Docs operations. Usage: admin:docs <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:docs [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `dev:entities`

**Purpose**: Manage Dev Entities operations. Usage: admin:entities <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:entities [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Bootstraps a full application execution context via Scheduler.


---

## `dev:forms`

**Purpose**: Manage Dev Forms operations. Usage: admin:forms <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:forms [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Bootstraps a full application execution context via Scheduler.


---

## `dev:general`

**Purpose**: Manage Dev General operations. Usage: admin:general <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:general [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPPMod\SPPAPI\LiveAction.


---

## `dev:iam`

**Purpose**: Manage Dev IAM operations. Usage: admin:iam <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:iam [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, \SPPMod\SPPAuth\SPPGroup, secret.


---

## `dev:legacy`

**Purpose**: Manage Dev Legacy operations. Usage: admin:legacy <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:legacy [OPTIONS]
```

### Options Available
- `--apps` : Boolean flag or option. Extracted via static analysis.
- `--enable_api` : Boolean flag or option. Extracted via static analysis.
- `--columns` : Boolean flag or option. Extracted via static analysis.
- `--fields` : Boolean flag or option. Extracted via static analysis.
- `--modules` : Boolean flag or option. Extracted via static analysis.
- `--name` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \\Exception, record, \SPPMod\SPPDB\SPPDB, RecursiveIteratorIterator, RecursiveDirectoryIterator, \ReflectionClass, \SPP\Module.
- Makes outbound HTTP requests to external APIs or services.


---

## `dev:lifecycle`

**Purpose**: Manage Dev Lifecycle operations. Usage: admin:lifecycle <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:lifecycle [OPTIONS]
```

### Options Available
- `--environments` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPP\Module.
- Makes outbound HTTP requests to external APIs or services.


---

## `dev:modules`

**Purpose**: Manage Dev Modules operations. Usage: admin:modules <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:modules [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPP\Module.


---

## `dev:routing`

**Purpose**: Manage Dev Routing operations. Usage: admin:routing <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:routing [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `dev:spplang`

**Purpose**: Manage Dev spplang operations. Usage: admin:spplang <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:spplang [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: translation.


---

## `dev:xdb`

**Purpose**: Manage Dev XDB operations. Usage: admin:xdb <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:xdb [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB, \SPPMod\SPPXDB\MigrationManager, \SPPMod\SPPXDB\SeederManager.


---

## `di:list`

**Purpose**: List the Dependency Injection container bindings

### Synopsis
```bash
php spp.php di:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \ReflectionClass.


---

## `diff:apply`

**Purpose**: Apply a patch or delta file

### Synopsis
```bash
php spp.php diff:apply [OPTIONS]
```

### Extended Usage
```text
Usage: diff:apply --file=patch.json

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `diff:compare`

**Purpose**: Compare two JSON arrays or states

### Synopsis
```bash
php spp.php diff:compare [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php diff:compare --type=<ModelClass> --id=<ID> --rev=<RevID> [--json]

```

### Options Available
- `--type=` : Expects a value. Extracted via static analysis.
- `--id=` : Expects a value. Extracted via static analysis.
- `--rev=` : Expects a value. Extracted via static analysis.
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `diff:history`

**Purpose**: View revision history of an entity

### Synopsis
```bash
php spp.php diff:history [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php diff:history --type=<ModelClass> --id=<ID> [--json]

```

### Options Available
- `--type=` : Expects a value. Extracted via static analysis.
- `--id=` : Expects a value. Extracted via static analysis.
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `diff:rollback`

**Purpose**: Rollback an entity to a previous state

### Synopsis
```bash
php spp.php diff:rollback [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php diff:rollback --type=<ModelClass> --id=<ID> --rev=<RevID>

```

### Options Available
- `--type=` : Expects a value. Extracted via static analysis.
- `--id=` : Expects a value. Extracted via static analysis.
- `--rev=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `docs:api`

**Purpose**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:api [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `docs:build`

**Purpose**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:build [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `docs:man`

**Purpose**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:man [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `docs:openapi`

**Purpose**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:openapi [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `docs:phpdoc`

**Purpose**: Documentation utilities (build, api, openapi, man, phpdoc).

### Synopsis
```bash
php spp.php docs:phpdoc [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDoc\SPPDocGenerator.


---

## `doctor`

**Purpose**: Diagnose the health of the WebOS architecture

### Synopsis
```bash
php spp.php doctor [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `drishyam:clear`

**Purpose**: Clear the Drishyam rendering cache

### Synopsis
```bash
php spp.php drishyam:clear [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `drishyam:compile`

**Purpose**: Pre-compile Drishyam templates for production

### Synopsis
```bash
php spp.php drishyam:compile [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `drishyam:theme:check`

**Purpose**: Validate Drishyam theme assets and structure

### Synopsis
```bash
php spp.php drishyam:theme:check [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `ent:edit`

**Purpose**: Edit an existing SPPEntity definition

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

### Options Available
- `--table=` : Expects a value. Extracted via static analysis.
- `--extends=` : Expects a value. Extracted via static analysis.
- `--login=` : Expects a value. Extracted via static analysis.
- `--add-field=` : Expects a value. Extracted via static analysis.
- `--remove-field=` : Expects a value. Extracted via static analysis.
- `--add-relation=` : Expects a value. Extracted via static analysis.
- `--remove-relation=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `entity:crud`

**Purpose**: Manage SPP entities (list, create, edit, delete)

### Synopsis
```bash
php spp.php entity:crud [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `env:backup`

**Purpose**: Backup all environment configurations

### Synopsis
```bash
php spp.php env:backup [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \ZipArchive.


---

## `env:get`

**Purpose**: Get a specific configuration variable

### Synopsis
```bash
php spp.php env:get [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php env:get <key> [--app=appname]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `env:list`

**Purpose**: List all environment and configuration variables for an app context

### Synopsis
```bash
php spp.php env:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \ReflectionClass.


---

## `env:mode`

**Purpose**: Switch environment error reporting mode between dev (Ignition errors) and prod (500 pages)

### Synopsis
```bash
php spp.php env:mode [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `env:set`

**Purpose**: Set a specific configuration variable

### Synopsis
```bash
php spp.php env:set [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php env:set <key> <value> [--app=appname]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `env:status`

**Purpose**: Display system health and environment status

### Synopsis
```bash
php spp.php env:status [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `env:token:rotate`

**Purpose**: Rotate the system deployment token

### Synopsis
```bash
php spp.php env:token:rotate [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB.


---

## `event:dispatch`

**Purpose**: Alias for event:fire

### Synopsis
```bash
php spp.php event:dispatch [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php event:fire --event=<event_name> [--payload=<json>]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `event:fire`

**Purpose**: Trigger a specific event manually

### Synopsis
```bash
php spp.php event:fire [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php event:fire --event=<event_name> [--payload=<json>]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.
- `--event=` : Expects a value. Extracted via static analysis.
- `--payload=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `event:list-listeners`

**Purpose**: List all registered global event listeners

### Synopsis
```bash
php spp.php event:list-listeners [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \ReflectionClass.


---

## `ext:disable`

**Purpose**: Disable a specific extension

### Synopsis
```bash
php spp.php ext:disable [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ext:disable <extension_name>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `ext:enable`

**Purpose**: Enable a specific extension

### Synopsis
```bash
php spp.php ext:enable [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ext:enable <extension_name>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `ext:install`

**Purpose**: Install an extension from a zip or directory

### Synopsis
```bash
php spp.php ext:install [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ext:install --source=<path_or_url>

```

### Options Available
- `--source=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `ext:list`

**Purpose**: List all available and installed extensions

### Synopsis
```bash
php spp.php ext:list [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


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
- `--extends=` : Expects a value. Extracted via static analysis.
- `--prefix=` : Expects a value. Extracted via static analysis.
- `--shared_groups` : Boolean flag or option. Extracted via static analysis.

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
- `--shared_group` : Boolean flag or option. Extracted via static analysis.

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
- `--extends=` : Expects a value. Extracted via static analysis.
- `--prefix=` : Expects a value. Extracted via static analysis.

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
- `--entities` : Boolean flag or option. Extracted via static analysis.

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
- `--locale=` : Expects a value. Extracted via static analysis.

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
- `--locale=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `iam:abac`

**Purpose**: Manage Attribute-Based Access Control (ABAC) policies

### Synopsis
```bash
php spp.php iam:abac [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php iam:abac --action=create --param1=\
```

### Options Available
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPDB.


---

## `iam:roles`

**Purpose**: List all Roles and Entity Role Assignments

### Synopsis
```bash
php spp.php iam:roles [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php iam:roles list

```

### Options Available
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPDB.


---

## `import:component`

**Purpose**: Imports pristine air-gapped sovereign UI components

### Synopsis
```bash
php spp.php import:component [OPTIONS]
```

### Options Available
- `--target=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `integration:install`

**Purpose**: Provision an external app directory and register the SPP route bypass

### Synopsis
```bash
php spp.php integration:install [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php integration:install <app_name> <route_path> [--isolation=virtual|physical]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `integration:queue:work`

**Purpose**: Run the persistent CDC integration event queue worker

### Synopsis
```bash
php spp.php integration:queue:work [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: DBAdapter.


---

## `integration:restore`

**Purpose**: Time-travel a user state to a historical point using CQRS Event Sourcing

### Synopsis
```bash
php spp.php integration:restore [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php integration:restore <user_id> <timestamp_or_snapshot_id>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `integration:seed`

**Purpose**: Bulk seed local SPP users into a specific integration target

### Synopsis
```bash
php spp.php integration:seed [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php integration:seed <app_name>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: DBAdapter.


---

## `interdb:config`

**Purpose**: Get or set the interdb operating mode

### Synopsis
```bash
php spp.php interdb:config [OPTIONS]
```

### Options Available
- `--mappings` : Boolean flag or option. Extracted via static analysis.

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
- `--mappings` : Boolean flag or option. Extracted via static analysis.

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

## `kernel:compile`

**Purpose**: Compiles the WebOS Kernel into the FastCGI performance cache.

### Synopsis
```bash
php spp.php kernel:compile [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `lang:export`

**Purpose**: Export active database translation overrides into JSON language file

### Synopsis
```bash
php spp.php lang:export [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Dynamically loads SPP kernel modules: spplang.
- Bootstraps a full application execution context via Scheduler.


---

## `lang:import`

**Purpose**: Import JSON language file into active database translation overrides

### Synopsis
```bash
php spp.php lang:import [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: spplang.
- Bootstraps a full application execution context via Scheduler.


---

## `lang:list`

**Purpose**: List all translations

### Synopsis
```bash
php spp.php lang:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

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

## `lint`

**Purpose**: Run SPP native linter on a file

### Synopsis
```bash
php spp.php lint [OPTIONS]
```

### Options Available
- `--file=` : Expects a value. Extracted via static analysis.
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


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
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--channel=` : Expects a value. Extracted via static analysis.
- `--event=` : Expects a value. Extracted via static analysis.
- `--payload=` : Expects a value. Extracted via static analysis.

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

## `make:app`

**Purpose**: Create a new SPP application context

### Synopsis
```bash
php spp.php make:app [OPTIONS]
```

### Options Available
- `--enterprise` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: SPP.


---

## `make:app-legacy`

**Purpose**: Legacy scaffolder — forwards to make:app (kept for backward compatibility)

### Synopsis
```bash
php spp.php make:app-legacy [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: MakeAppCommand.


---

## `make:blade`

**Purpose**: Scaffold a new Blade template (Drishyam Paradigm)

### Synopsis
```bash
php spp.php make:blade [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:blade <ViewName>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: Drishyam, Blade.


---

## `make:blade-project`

**Purpose**: Scaffold a new Blade-enabled SPP application

### Synopsis
```bash
php spp.php make:blade-project [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:blade-project <app_name>

```

### Options Available
- `----force` : Boolean flag or option. Extracted via static analysis.
- `--logout` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: Blade, SPP, app.


---

## `make:blade-scaffold`

**Purpose**: Create a full stack Blade scaffold (Entity, YAML Form, Controller, Blade Views)

### Synopsis
```bash
php spp.php make:blade-scaffold [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `make:command`

**Purpose**: Create a new CLI command class

### Synopsis
```bash
php spp.php make:command [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:command <name> [--app=appname] [--command=cmd:name]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: CLI.


---

## `make:command-test`

**Purpose**: Generate a boilerplate Parikshak feature test for a given command

### Synopsis
```bash
php spp.php make:command-test [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:command-test <CommandName> [--app=appname]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `make:controller`

**Purpose**: Create a new controller class

### Synopsis
```bash
php spp.php make:controller [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:controller <name> [--app=appname] [--resource]

```

### Options Available
- `--resource` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: controller.


---

## `make:deployment`

**Purpose**: Generate Enterprise Docker and K8s scaffolding for the application.

### Synopsis
```bash
php spp.php make:deployment [OPTIONS]
```

### Options Available
- `--with-redis` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `make:dotnet-service`

**Purpose**: Create a new .NET service project

### Synopsis
```bash
php spp.php make:dotnet-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:dotnet-service <name> [--app=context]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: console.


---

## `make:drupal-bridge`

**Purpose**: Scaffold a Drupal module to bridge SPP into Drupal

### Synopsis
```bash
php spp.php make:drupal-bridge [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: TwigFunction.


---

## `make:entity`

**Purpose**: Create a new SPPEntity definition

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

### Options Available
- `--fields=` : Expects a value. Extracted via static analysis.
- `--app=` : Expects a value. Extracted via static analysis.
- `--table=` : Expects a value. Extracted via static analysis.
- `--extends=` : Expects a value. Extracted via static analysis.
- `--login=` : Expects a value. Extracted via static analysis.
- `--relations=` : Expects a value. Extracted via static analysis.
- `--api` : Boolean flag or option. Extracted via static analysis.
- `--resource` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: SPPEntity.


---

## `make:event`

**Purpose**: Create a new event entry and scaffold its handler

### Synopsis
```bash
php spp.php make:event [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:event <EventName> <HandlerClassName> [--app=appname] [--overridable] [--default-handler]

```

### Options Available
- `--overridable` : Boolean flag or option. Extracted via static analysis.
- `--default-handler` : Boolean flag or option. Extracted via static analysis.
- `--events` : Boolean flag or option. Extracted via static analysis.
- `--listeners` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: event.


---

## `make:eventhand`

**Purpose**: Create a new Event Handler class

### Synopsis
```bash
php spp.php make:eventhand [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:eventhand <HandlerClassName> [--app=appname]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.
- Instantiates internal components: Event.


---

## `make:form`

**Purpose**: Create a new SPP form definition

### Synopsis
```bash
php spp.php make:form [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:form <name> [--app=appname]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPP.


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

## `make:live-component`

**Purpose**: Create a new Live Component class

### Synopsis
```bash
php spp.php make:live-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:live-component <name> [--app=appname]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: Live.


---

## `make:middleware`

**Purpose**: Create a new middleware class

### Synopsis
```bash
php spp.php make:middleware [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:middleware <name> [--app=appname]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: middleware.


---

## `make:migration`

**Purpose**: Create a new database migration file

### Synopsis
```bash
php spp.php make:migration [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: database.


---

## `make:mixed-paradigm`

**Purpose**: Scaffold a Kitchen Sink view blending SPPView, Drishyam, and SPPUX

### Synopsis
```bash
php spp.php make:mixed-paradigm [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:mixed-paradigm <ViewName>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: Drishyam.


---

## `make:model`

**Purpose**: Create a new model class (Fluent-ready)

### Synopsis
```bash
php spp.php make:model [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:model <name> [--app=appname] [--table=tablename]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: model.


---

## `make:module`

**Purpose**: Create a new SPP module

### Synopsis
```bash
php spp.php make:module [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:module <name> [--scope=spp|contrib|app]

```

### Options Available
- `--scope=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: SPP.


---

## `make:node-service`

**Purpose**: Create a new Node.js service script

### Synopsis
```bash
php spp.php make:node-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:node-service <name> [--app=context]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: Node.


---

## `make:partial`

**Purpose**: Scaffold a new external view partial template (HTML/PHP/JS)

### Synopsis
```bash
php spp.php make:partial [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:partial <PartialName.html|.php|.js> [--app=AppName]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: external.


---

## `make:perl-service`

**Purpose**: Create a new Perl service script

### Synopsis
```bash
php spp.php make:perl-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:perl-service <name> [--app=context]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: Perl.


---

## `make:polyglot`

**Purpose**: Scaffold a new polyglot service (e.g. php spp.php make:polyglot python MyService)

### Synopsis
```bash
php spp.php make:polyglot [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:polyglot <language> <service_name> [--app=context]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: polyglot.


---

## `make:polyglot-partial`

**Purpose**: Scaffold a new external polyglot partial service file (Python/Node/Go)

### Synopsis
```bash
php spp.php make:polyglot-partial [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:polyglot-partial <ModuleName.py|.js|.go> [--lang=python|node|go] [--app=AppName]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: external.


---

## `make:python-service`

**Purpose**: Create a new Python service script

### Synopsis
```bash
php spp.php make:python-service [OPTIONS]
```

### Extended Usage
```text
Usage: spp make:python-service <name> [--app=context]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: Python.


---

## `make:react-component`

**Purpose**: Scaffold a new React component (ESM/No-build)

### Synopsis
```bash
php spp.php make:react-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:react-component <ComponentName>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: React.


---

## `make:scaffold`

**Purpose**: Create a full stack scaffold (Entity, DB, Controller, View)

### Synopsis
```bash
php spp.php make:scaffold [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `make:seeder`

**Purpose**: Create a new Database Seeder class

### Synopsis
```bash
php spp.php make:seeder [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: Database.


---

## `make:service`

**Purpose**: Create a new service class

### Synopsis
```bash
php spp.php make:service [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:service <name> [--app=appname] [--lang=python]

```

### Options Available
- `--lang=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: service, MakePythonCommand, MakeNodeCommand, MakeGoCommand, MakeDotNetCommand, MakePerlCommand, MakeJavaCommand.


---

## `make:sppview`

**Purpose**: Scaffold a new native AST SPPView template

### Synopsis
```bash
php spp.php make:sppview [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:sppview <ViewName>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: native.


---

## `make:stream`

**Purpose**: Scaffold a new external Turbo Stream template

### Synopsis
```bash
php spp.php make:stream [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:stream <StreamName.html|.php|.blade.php> [--app=AppName]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: external.


---

## `make:twig`

**Purpose**: Scaffold a new Twig template (Drishyam Paradigm)

### Synopsis
```bash
php spp.php make:twig [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:twig <ViewName>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: Drishyam, Twig.


---

## `make:ux-component`

**Purpose**: Scaffold a new SPP-UX reactive component

### Synopsis
```bash
php spp.php make:ux-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:ux-component <ComponentName> [--template=external]

```

### Options Available
- `--template=external` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: reactive, SPP.


---

## `make:view`

**Purpose**: Create a new view definition (equivalent to Drupal Views).

### Synopsis
```bash
php spp.php make:view [OPTIONS]
```

### Options Available
- `--table=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: view, \SPPMod\SPPDB\SPPDB.


---

## `make:vue-component`

**Purpose**: Scaffold a new Vue 3 component (ESM/No-build)

### Synopsis
```bash
php spp.php make:vue-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:vue-component <ComponentName>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: Vue.


---

## `make:wizard`

**Purpose**: Scaffold a modern WizardController, workflow config, and partials

### Synopsis
```bash
php spp.php make:wizard [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:wizard <WizardName> [--app=AppName]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `man`

**Purpose**: Format and display manual pages for SPP commands

### Synopsis
```bash
php spp.php man [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: elements.


---

## `man:generate`

**Purpose**: Generate highly detailed man-pages in Markdown and UNIX roff formats

### Synopsis
```bash
php spp.php man:generate [OPTIONS]
```

### Options Available
- `--force` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \ReflectionClass.
- Makes outbound HTTP requests to external APIs or services.
- Interacts with the application cache layer (Redis/Memcached).


---

## `manifest:export`

**Purpose**: Exports tool autodiscovery definitions for AI Copilots

### Synopsis
```bash
php spp.php manifest:export [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `mesh:add`

**Purpose**: Mounts a legacy application as a passthrough route in the WebOS Mesh

### Synopsis
```bash
php spp.php mesh:add [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `mesh:list`

**Purpose**: Lists all active Mesh passthrough routes

### Synopsis
```bash
php spp.php mesh:list [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `mesh:remove`

**Purpose**: Unmounts a legacy application from the WebOS Mesh

### Synopsis
```bash
php spp.php mesh:remove [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `mesh:update`

**Purpose**: Updates features for an existing mesh route

### Synopsis
```bash
php spp.php mesh:update [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `middleware:list`

**Purpose**: List the middleware pipeline for an app

### Synopsis
```bash
php spp.php middleware:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \ReflectionClass.


---

## `migrate`

**Purpose**: Run pending database migrations

### Synopsis
```bash
php spp.php migrate [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPPMigrationManager.


---

## `migrate:make`

**Purpose**: Generate a new database migration class.

### Synopsis
```bash
php spp.php migrate:make [OPTIONS]
```

### Options Available
- `--name=` : Expects a value. Extracted via static analysis.
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: database.


---

## `module:disable`

**Purpose**: Disable an SPP module

### Synopsis
```bash
php spp.php module:disable [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:disable <modulename>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `module:enable`

**Purpose**: Enable an SPP module

### Synopsis
```bash
php spp.php module:enable [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:enable <modulename>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `module:install`

**Purpose**: Install or upgrade a specific module or all active modules

### Synopsis
```bash
php spp.php module:install [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:install <modulename> [--all]

```

### Options Available
- `--all` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `module:list`

**Purpose**: Discovers and tabulates active kernel framework modules

### Synopsis
```bash
php spp.php module:list [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `module:setting:list`

**Purpose**: List all settings for a given module

### Synopsis
```bash
php spp.php module:setting:list [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `module:setting:update`

**Purpose**: Update a configuration setting for a specific module

### Synopsis
```bash
php spp.php module:setting:update [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `module:uninstall`

**Purpose**: Uninstall a module (drops tracking but retains data tables)

### Synopsis
```bash
php spp.php module:uninstall [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:uninstall <modulename>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `module:update`

**Purpose**: Execute the update hook for a specific module

### Synopsis
```bash
php spp.php module:update [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php module:update <modulename> [--from=1.0] [--to=1.1]

```

### Options Available
- `--from=` : Expects a value. Extracted via static analysis.
- `--to=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `oauth:client:create`

**Purpose**: Create a new OAuth 2.0 Client App

### Synopsis
```bash
php spp.php oauth:client:create [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php oauth:client:create --name=\
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: OAuth, SPPDB.


---

## `oauth:client:delete`

**Purpose**: Delete an OAuth 2.0 Client App

### Synopsis
```bash
php spp.php oauth:client:delete [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php oauth:client:delete <id>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPDB.


---

## `oauth:client:list`

**Purpose**: List all OAuth 2.0 Client Apps

### Synopsis
```bash
php spp.php oauth:client:list [OPTIONS]
```

### Options Available
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPDB.


---

## `polyglot:async`

**Purpose**: Internal command to execute polyglot calls asynchronously

### Synopsis
```bash
php spp.php polyglot:async [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `polyglot:list`

**Purpose**: Discovers and tabulates all registered polyglot services

### Synopsis
```bash
php spp.php polyglot:list [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \RecursiveIteratorIterator, \RecursiveDirectoryIterator.


---

## `polyglot:run`

**Purpose**: Executes a specific polyglot service directly

### Synopsis
```bash
php spp.php polyglot:run [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php polyglot:run --path=<relative_path_to_service> [args...]

```

### Options Available
- `--path=` : Expects a value. Extracted via static analysis.
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.


---

## `polyglot:status`

**Purpose**: Checks the runtime environment for polyglot language binaries

### Synopsis
```bash
php spp.php polyglot:status [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.


---

## `polyglot:worker`

**Purpose**: Manage Polyglot persistent workers

### Synopsis
```bash
php spp.php polyglot:worker [OPTIONS]
```

### Extended Usage
```text
Usage: spp polyglot:worker [start|stop|restart|status] <module> [<lang>]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.


---

## `profile:report:generate`

**Purpose**: Dump a performance profile trace for debugging

### Synopsis
```bash
php spp.php profile:report:generate [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


---

## `profile:status`

**Purpose**: Check if the performance profiler is running/enabled

### Synopsis
```bash
php spp.php profile:status [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `queue:list`

**Purpose**: List all jobs currently in the queue

### Synopsis
```bash
php spp.php queue:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `queue:work`

**Purpose**: Starts a worker loop to process background jobs from the queue.

### Synopsis
```bash
php spp.php queue:work [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


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

## `serve`

**Purpose**: Start a local development server for the current application

### Synopsis
```bash
php spp.php serve [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.


---

## `serve:async`

**Purpose**: Boot the persistent memory asynchronous coroutine runtime (FrankenPHP/OpenSwoole)

### Synopsis
```bash
php spp.php serve:async [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.
- `--port=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


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

## `shell`

**Purpose**: Launch the interactive SPP Shell Mode (run all CLI commands, switch apps, inspect state, tabs, AI, polyglot, etc.).

### Synopsis
```bash
php spp.php shell [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPP\Core\InteractiveShell.


---

## `site:install`

**Purpose**: Initialize the database and load default configurations for a specific profile.

### Synopsis
```bash
php spp.php site:install [OPTIONS]
```

### Options Available
- `--profile=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `sys:debug`

**Purpose**: Toggle global framework debug mode (on|off)

### Synopsis
```bash
php spp.php sys:debug [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php sys:debug on|off

```

### Options Available
- `--settings` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).


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

## `sys:status`

**Purpose**: Displays framework health, environment diagnostics, and polyglot bridge status

### Synopsis
```bash
php spp.php sys:status [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `sys:test:auto`

**Purpose**: Runs Automated Evolutionary Testing (Parikshak) for the current application.

### Synopsis
```bash
php spp.php sys:test:auto [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, Parikshak.


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

## `test`

**Purpose**: Run Parikshak Unit and Feature Tests

### Synopsis
```bash
php spp.php test [OPTIONS]
```

### Options Available
- `--coverage` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, SPPTestRunner.


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
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: parikshak.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\Parikshak\Parikshak.


---

## `test:dry-run`

**Purpose**: Dry-run all registered commands to catch syntax and initialization errors

### Synopsis
```bash
php spp.php test:dry-run [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.


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
- `--app=` : Expects a value. Extracted via static analysis.
- `--entities` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: parikshak.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\Parikshak\Parikshak.


---

## `test:routes`

**Purpose**: Test route scanner

### Synopsis
```bash
php spp.php test:routes [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `test:run`

**Purpose**: Runs Parikshak evaluation for an entity or the whole suite

### Synopsis
```bash
php spp.php test:run [OPTIONS]
```

### Options Available
- `--coverage` : Boolean flag or option. Extracted via static analysis.

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
- `--force` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `userprofile:export`

**Purpose**: Export user profile data for compliance/GDPR

### Synopsis
```bash
php spp.php userprofile:export [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php userprofile:export --user=<user_id>

```

### Options Available
- `--user=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `userprofile:schema:update`

**Purpose**: Sync extended user profile metadata schemas

### Synopsis
```bash
php spp.php userprofile:schema:update [OPTIONS]
```

### Options Available
No static options detected for this command.

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
- `--app=` : Expects a value. Extracted via static analysis.
- `--name=` : Expects a value. Extracted via static analysis.
- `--url=` : Expects a value. Extracted via static analysis.
- `--source=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.
- `--name=` : Expects a value. Extracted via static analysis.
- `--source=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.
- `--name=` : Expects a value. Extracted via static analysis.
- `--script=` : Expects a value. Extracted via static analysis.
- `--method=` : Expects a value. Extracted via static analysis.
- `--source=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.
- `--name=` : Expects a value. Extracted via static analysis.
- `--source=` : Expects a value. Extracted via static analysis.

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
- `--app=` : Expects a value. Extracted via static analysis.
- `--name=` : Expects a value. Extracted via static analysis.
- `--payload=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.


---

## `workflow:dump`

**Purpose**: Dump a workflow definition as a visual state graph (Mermaid.js or Graphviz DOT)

### Synopsis
```bash
php spp.php workflow:dump [OPTIONS]
```

### Options Available
- `--format=` : Expects a value. Extracted via static analysis.
- `--file=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

## `workflow:process-timeouts`

**Purpose**: Process SLA timeouts on entities and trigger automatic escalation transitions

### Synopsis
```bash
php spp.php workflow:process-timeouts [OPTIONS]
```

### Options Available
- `--timeout` : Boolean flag or option. Extracted via static analysis.
- `--timeout_transition` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.


---

## `xdb:describe`

**Purpose**: Describe the schema of an XDB table

### Synopsis
```bash
php spp.php xdb:describe [OPTIONS]
```

### Extended Usage
```text
Usage: php spp xdb:describe <table_name> [--db=dbname]

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB.


---

## `xdb:list-dbs`

**Purpose**: List all available XDB databases

### Synopsis
```bash
php spp.php xdb:list-dbs [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB.


---

## `xdb:list-tables`

**Purpose**: List all tables in an XDB database

### Synopsis
```bash
php spp.php xdb:list-tables [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB.


---

## `xdb:make:migration`

**Purpose**: Create a new SPP_XDB migration file

### Synopsis
```bash
php spp.php xdb:make:migration [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php xdb:make:migration <name_of_table>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPP_XDB, MigrationManager.


---

## `xdb:make:seeder`

**Purpose**: Create a new SPP_XDB seeder file

### Synopsis
```bash
php spp.php xdb:make:seeder [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php xdb:make:seeder <name_of_seeder>

```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPP_XDB, SeederManager.


---

## `xdb:migrate`

**Purpose**: Run SPP_XDB Database Migrations

### Synopsis
```bash
php spp.php xdb:migrate [OPTIONS]
```

### Options Available
- `--steps=` : Expects a value. Extracted via static analysis.
- `--rollback` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPP_XDB, MigrationManager.


---

## `xdb:query`

**Purpose**: Execute a SQL or XPath query on the XML database

### Synopsis
```bash
php spp.php xdb:query [OPTIONS]
```

### Extended Usage
```text
Usage: php spp xdb:query \
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB.


---

## `xdb:seed`

**Purpose**: Run SPP_XDB Database Seeders

### Synopsis
```bash
php spp.php xdb:seed [OPTIONS]
```

### Options Available
- `--class=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPP_XDB, SeederManager.


---

## `xdb:shell`

**Purpose**: Launch the interactive SPPXDB shell

### Synopsis
```bash
php spp.php xdb:shell [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.


---

