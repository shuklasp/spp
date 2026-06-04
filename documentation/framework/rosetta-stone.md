# The SPP Rosetta Stone

Welcome to the SPP Framework! If you are a senior developer coming from mainstream frameworks like Laravel, Symfony, or Django, SPP's terminology can seem completely foreign. 

This guide serves as a "Rosetta Stone" to map the concepts you already know to SPP's custom ecosystem, drastically cutting down your learning curve.

---

## 1. The Database ORM / Data Mapping
* **Laravel**: Eloquent ORM
* **Symfony**: Doctrine ORM
* **SPP**: **XDB (XML Database Mapping)**
  * *How it works*: Instead of PHP models or migration classes, SPP defines all database schemas and relationships in XML files. The `SPPDB` engine dynamically parses these XML blueprints and generates/alters the physical SQL tables.

## 2. Command Line Tool
* **Laravel**: `php artisan`
* **Symfony**: `php bin/console`
* **SPP**: `php spp.php`
  * *Example*: `php spp.php list` (to see all commands) or `php spp.php make-command` (to scaffold).

## 3. The Core Concept of "Apps" vs "Modules"
* **Laravel**: Everything lives in `app/` and routes define the context.
* **SPP**: **Multi-Context Monolith**. SPP supports running multiple entirely separate applications inside the same codebase. 
  * You switch contexts dynamically using `\SPP\Scheduler::setContext('app_name')`. 
  * `Modules` (`spp/modules/`) are globally shared logic.
  * `Apps` (`src/app_name/`) are isolated instances.

## 4. Middleware & Hooks
* **Laravel**: Middleware
* **Symfony**: Event Subscribers / Kernel Events
* **SPP**: **Hooks & Middleware**
  * SPP supports standard middleware via `spp/core/class.middleware.php`.
  * It also heavily relies on a custom `EventDispatcher` using string-based hook names (e.g., `spp_before_view_render`).

## 5. UI and Form Generation
* **Laravel**: Blade + Livewire / Vue / React
* **Symfony**: Twig + Symfony Forms
* **SPP**: **Drishyam & SPPForms**
  * SPP uses `sppforms.js` for dynamic frontend validation and state management.
  * *Drishyam* is the built-in UI component generator.

## 6. Background Jobs & Caching
* **Laravel**: Queues & Redis Cache
* **SPP**: **Polyglot Daemons & Hybrid Registry**
  * For heavy background processing, SPP doesn't use standard PHP workers. It spawns **Polyglot Daemons** (e.g., Python or C++) that communicate with PHP instantly over TCP sockets.
  * For caching, SPP uses the `Registry` (`__shared=>` namespace) which uses File I/O by default, but seamlessly falls back to native Redis if the server supports it.

## 7. Configuration
* **Laravel**: `.env` and `config/*.php`
* **SPP**: `etc/settings.xml` and `etc/cli-settings.yml`

---

## The Golden Rule of SPP
SPP is designed to **minimize external dependencies**. If a standard framework uses a Composer package to solve a problem, SPP likely built a custom native engine for it (e.g., InterDB, XDB, Polyglot Bridge). Embrace the monolith!
