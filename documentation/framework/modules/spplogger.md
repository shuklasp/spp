# Core Module: SppLogger

SppLogger is the framework's centralized diagnostic and auditing system. It provides a structured way to capture errors, debugging information, and system events.

---

## 1. Basic Philosophy
SppLogger follows the **"Silent Guardian"** philosophy. It is designed to be low-overhead during normal operations while providing extremely deep diagnostic data when things go wrong. It supports multiple "Channels" and "Handlers" to route logs to different destinations (Files, Console, Database).

---

## 2. Architecture
The module is based on the **PSR-3 Logger Interface** standard.

### Key Components:
*   **Log Channels**: Categorized streams (e.g., `system`, `security`, `sql`).
*   **Log Levels**: Standardized severity levels (Emergency, Alert, Critical, Error, Warning, Notice, Info, Debug).
*   **Processors**: Automated data injectors that add context like Request URI, User ID, or Memory Usage to every log entry.

---

## 3. API & Usage

### Basic Logging
```php
use \SPP\Logger;

Logger::info("User logged in", ['uid' => 42]);
Logger::error("Database connection failed", ['host' => $host]);
```

### Contextual Logging
You can pass an array as the second argument to provide structured context:
```php
Logger::debug("Processing node", [
    'node_id' => $id,
    'memory_peak' => memory_get_peak_usage()
]);
```

---

## 4. Storage & Rotation
Logs are typically stored in the application's local `var/logs/` directory. The system supports automatic rotation and cleanup to prevent log files from exhausting disk space.

---
[Back to Modules Index](index.md)
