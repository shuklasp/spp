# Core Modules: State & Configuration (SppConfig, SppSetting, DbSettings)

These modules manage the framework's configuration lifecycle, from static YAML files to persistent database-backed settings.

---

## 1. Basic Philosophy
Configuration in SPP follows a **"Cascading Override"** model. Settings are gathered from multiple sources (Global YAML -> App YAML -> Database), with the most specific source taking precedence.

---

## 2. Architecture & Modules

### SppConfig (`\SPP\Config`)
Handles the reading and parsing of static configuration files (YAML). It provides the foundational settings required for the framework to boot.

### SppSetting & DbSettings
These modules provide **Persistent Runtime Configuration**. Unlike YAML files, settings stored here can be modified via the Admin UI at runtime without touching the filesystem.

---

## 3. API & Usage

### Accessing Configuration
```php
// Get a setting with a fallback default
$debug = \SPP\SPPConfig::get('system.debug', false);
```

### Working with Persistent Settings
```php
// Retrieve a runtime setting
$siteName = \SPP\Setting::get('site_name');

// Save a runtime setting
\SPP\Setting::set('maintenance_mode', true);
```

---

## 4. Cascading Logic
The resolution order for a setting key (e.g., `theme`) is:
1.  **Database** (`DbSettings`): Runtime overrides.
2.  **App Config** (`app.yml`): Application-specific defaults.
3.  **Global Config** (`global-settings.yml`): Framework-wide defaults.

---
[Back to Modules Index](index.md)
