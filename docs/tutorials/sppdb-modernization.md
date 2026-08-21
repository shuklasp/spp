# Modernizing your App with SPPDB

Welcome! If you are new to the SPP framework, you are in the right place. The SPP framework provides a powerful database abstraction layer called `sppdb` that makes interacting with your database secure, fast, and remarkably easy to use.

Recently, the SPP database layer was upgraded with **Enterprise-Grade** features designed to make your applications resilient. In this tutorial, you will learn exactly what these features are, how they work under the hood, and how to use them in your apps!

## 1. Connection Resilience (Auto-Reconnect)

### What is it?
When you write long-running background tasks (like Queue Workers or Daemons), the connection to the database might "time out" if there's no activity for a while. When your script wakes up and tries to run a query, the database responds with a *"MySQL server has gone away"* error, crashing your app.

### How SPPDB solves this natively
The new `PDOAdapter` inside `sppdb` automatically detects if the connection has dropped. If it detects a timeout (Errors 2006 or 2013), it **automatically rebuilds the connection in the background** and retries your query seamlessly. You don't have to write any extra code to handle this!

## 2. Handling High Concurrency (Deadlock Retries)

### What is a Deadlock?
When multiple users try to update the exact same records at the exact same millisecond, the database might panic and throw a "Deadlock" error. This is normal in large applications (like ticketing systems or flash sales).

### How to use Transaction Retries
SPPDB provides a `transaction()` method to ensure your queries all succeed together, or fail together (Rollback). Now, you can tell SPP to **automatically retry** the transaction if a deadlock occurs!

Just pass the number of retries you want as the second parameter:

```php
use SPPMod\SPPDB\SPPDB;

$db = new SPPDB();

// The "3" tells SPPDB to retry up to 3 times if it hits a deadlock
$db->transaction(function($db) {
    // 1. Deduct inventory
    $db->execute_query("UPDATE products SET stock = stock - 1 WHERE id = 5");
    
    // 2. Insert order
    $db->execute_query("INSERT INTO orders (product_id) VALUES (5)");
}, 3);
```

## 3. Strict Type Casting for Entities

### The Problem
When you fetch data from a database using standard PHP, numbers are often returned as strings (e.g., `"123"` instead of `123`). This makes strict type-checking in PHP difficult.

### The Solution
When using `SPPEntity` and `SppEntityQuery`, the framework now automatically reads your YAML configuration file (the entity's `attributes` schema). When data is pulled from the database, SPPDB **hydrates** (converts) those strings into their true PHP types:

*   **Ints** become true PHP `integers`
*   **Booleans** become true PHP `true/false`
*   **Floats/Decimals** become true PHP `floats`
*   **JSON columns** are automatically decoded into PHP `arrays`!

## 4. Dialect-Aware Quoting (Security)

Previously, SPPDB used Regular Expressions to strip bad characters from column names to prevent SQL Injection. While secure, this prevented developers from using reserved words (like `order` or `limit`) as column names.

Now, SPPDB uses **Compiler Abstraction**. Depending on whether you are using MySQL, PostgreSQL, or SQLite, SPPDB will wrap your column names in the correct quotes (like `` `backticks` `` for MySQL).

```php
// SPPDB now safely compiles this into: SELECT * FROM `users` WHERE `order` = 1
$db->table('users')->where('order', 1)->get();
```

## 5. N+1 Query Prevention (Strict Lazy Loading)

### The N+1 Problem
If you loop over 50 users and access their profile (`$user->profile`) without eager loading it first, your app executes 1 query for the users and 50 extra queries for the profiles. This "N+1" problem destroys app performance.

### The Solution: Strict Mode
SPPDB now allows you to completely disable lazy loading. When enabled, attempting to lazy load a relation will throw a `LazyLoadingViolationException` instead of quietly running a query. This forces developers to use eager loading (`with()`) during development, catching performance bugs before they reach production!

```php
use SPPMod\SPPDB\SPPEntity;

// Add this to your App's boot process (e.g., for local/staging environments)
SPPEntity::preventLazyLoading(true);
```

## 6. Nested Transactions (Savepoints)

### The Problem
In standard PDO, calling eginTransaction() twice causes a fatal error. This means if you write a function that wraps its logic in a transaction, and you call that function from *another* function that also has a transaction, your app will crash.

### The Solution: Native Savepoints
SPPDB now tracks your transaction depth. If you are already inside a transaction and call eginTransaction() again, SPPDB automatically uses a SQL SAVEPOINT. If the inner transaction fails, it safely rolls back *only* to that savepoint without destroying the outer transaction! This allows for truly modular, composable database logic.

## 7. Sticky Reads (Read-Your-Own-Writes Consistency)

### The Problem
When using a Master-Replica database setup (one DB for writes, another for reads), there is often replication lag. If you insert a user and immediately query for that user, the query might hit the read replica *before* the data has synced, returning nothing.

### The Solution: Sticky Sessions
SPPDB now implements **Sticky Reads**. The moment your application executes an INSERT, UPDATE, or DELETE query, the database adapter flips a switch. All subsequent SELECT queries for the remainder of the request will automatically route to the **Write Connection**. This perfectly guarantees "Read-Your-Own-Writes" consistency without manual intervention!

## Summary
By using the built-in `SPPDB` layer and extending `SPPEntity`, your SPP applications are automatically shielded from network drops, database deadlocks, N+1 query bottlenecks, and type-mismatch bugs—allowing you to focus entirely on building great features!


