# Core Module: SppDb

SppDb is the framework's database abstraction layer. It provides a secure, consistent, and high-performance interface for communicating with various database backends (PDO, MySQL, SQLite, etc.).

---

## 1. Basic Philosophy
SppDb is built on the **"Zero-Leak"** philosophy. It aims to abstract away the quirks of specific database engines while providing a unified API that handles connection pooling, automatic escaping, and transactional integrity by default.

---

## 2. Architecture
The module is centered around the `\SPP\DB` class.

### Key Components:
*   **Connection Manager**: Handles the lifecycle of database connections based on application context.
*   **Driver Layer**: Translates abstract framework calls into engine-specific SQL.
*   **Transaction Handler**: Provides a nested transaction model to ensure data atomicity.

---

## 3. API & Usage

### Executing Queries
```php
use \SPP\DB;

// Fetching results
$rows = DB::query("SELECT * FROM users WHERE status = ?", ['active']);

// Fetching a single row
$user = DB::row("SELECT * FROM users WHERE id = :id", ['id' => 1]);

// Fetching a single value
$count = DB::value("SELECT COUNT(*) FROM nodes");
```

### Writing Data
```php
// Insert
$id = DB::insert('logs', [
    'message' => 'Action performed',
    'created_at' => date('Y-m-d H:i:s')
]);

// Update
DB::update('users', ['status' => 'inactive'], 'id = ?', [42]);

// Delete
DB::delete('sessions', 'last_access < ?', [$timeout]);
```

---

## 4. Security & Best Practices
*   **Prepared Statements**: SppDb enforces prepared statements for all queries, making SQL injection nearly impossible if used correctly.
*   **Context Scoping**: Database prefixes are automatically handled based on the application's registry settings.
*   **Lazy Connection**: The database connection is only established when the first query is actually executed.

---
[Back to Modules Index](index.md)
