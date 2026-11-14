# Book 3 Chapter 4 — Dialects and Driver-Specific Compilation

## 1. Why Dialects Exist

Different database engines do not accept identical SQL syntax, feature sets, or storage types. Even basic operations like escaping a table name or calculating an AI vector similarity distance vary fundamentally between major relational database management systems.

A premier enterprise framework must insulate application developers from these differences, providing a uniform, portable active record and query interface while compiling to optimal native SQL for each engine.

```mermaid
flowchart TD
    A["SppEntityQuery Specification\n(Portable Application Code)"] --> B{"Dialect Selector\n(Connection DSN)"}
    B -->|MySQL / MariaDB| C["MySQLCompiler\n`table`.`column`\nDISTANCE(..., CAST(? AS VECTOR))\nLIMIT offset, count"]
    B -->|PostgreSQL| D["PostgresCompiler\n\"table\".\"column\"\n(\"col\" <=> ?::vector)\nLIMIT count OFFSET offset"]
    B -->|SQLite / XDB| E["SQLiteCompiler\n\"table\".\"column\"\nvec_distance_cosine(\"col\", ?)\nLIMIT count OFFSET offset"]
    C --> F["MySQL Engine"]
    D --> G["PostgreSQL Engine"]
    E --> H["SQLite WAL Database"]
```

---

## 2. Core Dialect Comparison Matrix

The following table summarizes how SPP's compiler subsystem translates common SQL operations across supported dialects:

| Feature | MySQLCompiler | PostgresCompiler | SQLiteCompiler |
|---|---|---|---|
| **Identifier Quoting** | `` `table`.`column` `` | `"table"."column"` | `"table"."column"` |
| **Pagination Syntax** | `LIMIT offset, rowcount` | `LIMIT rowcount OFFSET offset` | `LIMIT rowcount OFFSET offset` |
| **Vector Cosine Distance** | `DISTANCE(`col`, CAST(? AS VECTOR), 'COSINE') <= ?` | `("col" <=> ?::vector) < ?` | `vec_distance_cosine("col", ?) <= ?` |
| **Vector Euclidean (L2)** | `DISTANCE(`col`, CAST(? AS VECTOR), 'EUCLIDEAN') <= ?` | `("col" <-> ?::vector) < ?` | `vec_distance_L2("col", ?) <= ?` |
| **Vector Dot Product** | `DISTANCE(`col`, CAST(? AS VECTOR), 'DOT') <= ?` | `("col" <#> ?::vector) < ?` | `vec_distance_dot("col", ?) <= ?` |
| **Boolean Literals** | `1` / `0` (`TINYINT(1)`) | `TRUE` / `FALSE` (`BOOLEAN`) | `1` / `0` (`INTEGER`) |
| **Auto-Increment PK** | `AUTO_INCREMENT` | `BIGSERIAL` / `GENERATED ALWAYS AS IDENTITY` | `AUTOINCREMENT` |
| **Savepoints Support** | `SAVEPOINT name` | `SAVEPOINT name` | `SAVEPOINT name` |

---

## 3. Deep Dive: Dialect-Specific Vector Similarity Search

Artificial Intelligence (AI) and Retrieval-Augmented Generation (RAG) workloads require searching billions of high-dimensional vector embeddings based on semantic similarity. Each major database ecosystem approaches vector math differently. SPP abstracts these divergent implementations behind a single fluent method:

```php
$similarCourses = Course::query()
    ->whereSemanticSimilarity('embedding', $queryVector, 'cosine', 0.85, 5)
    ->get();
```

Here is what the SPP compiler generates under the hood for each dialect:

### PostgreSQL (`pgvector`)
PostgreSQL uses the industry-standard `pgvector` extension with custom binary distance operators:
```sql
SELECT * FROM "courses"
WHERE ("embedding" <=> ?::vector) < ?
ORDER BY "embedding" <=> ?::vector ASC
LIMIT 5 OFFSET 0
```
- Parameters: `['[0.12, 0.45, -0.78]', 0.15]` (Distance threshold $= 1.0 - \text{similarity} = 0.15$).

### SQLite (`sqlite-vec`)
SQLite uses the high-performance `sqlite-vec` C-extension providing scalar distance functions:
```sql
SELECT * FROM "courses"
WHERE vec_distance_cosine("embedding", ?) <= ?
ORDER BY vec_distance_cosine("embedding", ?) ASC
LIMIT 5 OFFSET 0
```
- Parameters: `['[0.12, 0.45, -0.78]', 0.15]`.

### MySQL 9.0+
MySQL 9.0 introduced native vector column types and the built-in `DISTANCE()` function:
```sql
SELECT * FROM `courses`
WHERE DISTANCE(`embedding`, CAST(? AS VECTOR), 'COSINE') <= ?
ORDER BY DISTANCE(`embedding`, CAST(? AS VECTOR), 'COSINE') ASC
LIMIT 0, 5
```
- Parameters: `['[0.12, 0.45, -0.78]', 0.15]`.

Application developers never need to remember these differences; the framework handles vector formatting, operator mapping, distance inversion, and ordering automatically.

---

## 4. Identifier Quoting & Reserved Words

A subtle source of bugs in multi-database applications is reserved keyword collisions (e.g. naming a column `group`, `order`, `key`, or `status`).

Each SPP compiler implements strict identifier sanitization:

```php
// MySQLCompiler:
public function quoteIdentifier(string $identifier): string
{
    $parts = explode('.', $identifier);
    return implode('.', array_map(fn($part) => '`' . str_replace('`', '``', $part) . '`', $parts));
}

// PostgresCompiler & SQLiteCompiler:
public function quoteIdentifier(string $identifier): string
{
    $parts = explode('.', $identifier);
    return implode('.', array_map(fn($part) => '"' . str_replace('"', '""', $part) . '"', $parts));
}
```

This prevents accidental syntax errors when application domain models utilize natural English nouns for column names.

---

## 5. Correlated Subqueries & RAW Condition Handling

When queries use relational methods like `whereHas()`, the compiler integrates correlated subqueries with the `RAW` operator:

```php
$activeTutors = User::query()
    ->where('role', 'tutor')
    ->whereHas('sessions', function($q) {
        $q->where('status', 'scheduled');
    })
    ->get();
```

Compiled query output by dialect:

### PostgreSQL
```sql
SELECT * FROM "users" 
WHERE "role" = ? 
  AND EXISTS (
      SELECT 1 FROM "sessions" 
      WHERE "sessions"."status" = ? 
        AND "sessions"."tutor_id" = "users"."id"
  )
```

### MySQL
```sql
SELECT * FROM `users` 
WHERE `role` = ? 
  AND EXISTS (
      SELECT 1 FROM `sessions` 
      WHERE `sessions`.`status` = ? 
        AND `sessions`.`tutor_id` = `users`.`id`
  )
```

Notice how table names, column references, and foreign key correlations are quoted according to the selected dialect, ensuring 100% syntactical compliance.

---

## 6. Hands-On Lab: Dialect Switching in Action

In this lab, you will manually compile the same `SppEntityQuery` using all three dialect compilers to verify their output.

Create a scratch script in your test environment:

```php
use SPPMod\SPPDB\SppEntityQuery;
use SPPMod\SPPDB\Compilers\MySQLCompiler;
use SPPMod\SPPDB\Compilers\PostgresCompiler;
use SPPMod\SPPDB\Compilers\SQLiteCompiler;

$query = new SppEntityQuery('users');
$query->condition('status', '=', 'active')
      ->order('id', 'DESC')
      ->limit(10, 20); // 10 rows starting at offset 20

$mysql = new MySQLCompiler();
$postgres = new PostgresCompiler();
$sqlite = new SQLiteCompiler();

echo "MySQL SQL:\n" . $mysql->compileSelect($query) . "\n\n";
echo "PostgreSQL SQL:\n" . $postgres->compileSelect($query) . "\n\n";
echo "SQLite SQL:\n" . $sqlite->compileSelect($query) . "\n";
```

### Expected Output:

```sql
MySQL SQL:
SELECT * FROM `users` WHERE `status` = ? ORDER BY `id` DESC LIMIT 20, 10

PostgreSQL SQL:
SELECT * FROM "users" WHERE "status" = ? ORDER BY "id" DESC LIMIT 10 OFFSET 20

SQLite SQL:
SELECT * FROM "users" WHERE "status" = ? ORDER BY "id" DESC LIMIT 10 OFFSET 20
```

Notice the crucial difference: MySQL compiles `LIMIT 20, 10` (offset, count), whereas PostgreSQL and SQLite compile `LIMIT 10 OFFSET 20` (count OFFSET offset).

---

## 7. Failure Lab: Unsupported Vector Metric

Attempt to compile a vector query with an unsupported distance metric:

```php
$compiler = new PostgresCompiler();
$query = new SppEntityQuery('documents');

// 'manhattan' metric is not implemented
$compiler->compileVectorSimilarity('embedding', [0.1, 0.2], 'manhattan', 0.5, 5);
```

**Observation**:
The compiler immediately throws `\InvalidArgumentException: Unsupported distance metric: manhattan`.
This protects against malformed SQL being sent to the database at runtime.

---

## 8. Kernel Hacker

- Review `spp/modules/spp/sppdb/Compilers/` to explore the exact class implementations.
- Notice how `SafeDDLRunner` interacts with dialect compilers to generate shadow tables across different engines.
- Inspect how `SppEntityQuery::getCompiler()` automatically resolves the active connection's dialect using driver sniffing.

---

## Checkpoint

> **Dialect compilers make portability practical. They allow application code to be written once with clean, high-level Active Record expressions while ensuring that every database engine executes the exact syntax and optimizations it natively expects.**
