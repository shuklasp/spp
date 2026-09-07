---
title: CLI SAPI Security & Daemon Architecture
duration: 20 mins
summary: Learn strict CLI SAPI guarding and how to build secure system commands and daemons.
---

# CLI SAPI Security & Daemon Architecture

High-privilege routines such as database migrations, background queue workers, and deployment orchestration must never be reachable via web HTTP requests.

## 1. The `isCLIOnly()` Security Guard

SPP enforces strict CLI SAPI guarding. Any command executing system operations or file modifications must override:

```php
public function isCLIOnly(): bool
{
    return true;
}
```

When `CommandManager::execute()` runs, it checks `PHP_SAPI !== 'cli'`. If accessed over HTTP, execution terminates immediately with a security block.

## 2. DDL Identifier Sanitization

When writing schema migrations or inspection tools, never interpolate raw strings into SQL DDL:

```php
// ✅ Correct: Strict sanitization
$safeTable = SchemaValidator::isValidIdentifier($tableName);
$sql = "DROP TABLE IF EXISTS " . $this->escapeIdentifier($safeTable);
```

## 3. Distributed Mutex Locking

All deployment commands prevent race conditions using distributed mutex locks:

```php
try {
    \SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock();
    // Execute critical deployment routine
} finally {
    \SPPMod\SPPDeploy\Deployer\TargetConnection::releaseDeploymentLock();
}
```