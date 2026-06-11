# SPP_XDB: Extensible Database Engine

SPP_XDB is the official database engine for the SPP Framework. It is an extremely modular, file-based database system that offers two distinct storage backends (XML and SQLite) while maintaining a unified feature set like field-level AES encryption, granular permissions (ACL), and high-availability Raft clustering.

## Multi-Engine Architecture

SPP_XDB uses a **Facade/Proxy Architecture**. The `SPP_XDB` class dynamically delegates operations to a specific storage backend.

You can switch the engine globally via configuration without changing any models or queries:

```php
// Use the classic XML engine (Default)
\SPP\SPPConfig::set('sys:db.engine', 'xml');

// Use the high-performance SQLite engine
\SPP\SPPConfig::set('sys:db.engine', 'sqlite');
```

### 1. XML Engine (`Engines\XMLEngine.php`)
The classic engine stores data in `.xml` files and indexes in `.json` files. It relies on a custom regex-based SQL-to-XPath translation engine. Best used for hierarchical data operations where native XPath queries (`$db->queryX()`) are heavily utilized.

### 2. SQLite Engine (`Engines\SQLiteEngine.php`)
A native `PDO`-backed engine storing data in `database.sqlite` files. It relies on SQLite's C-level implementation for standard SQL parsing, ACID transactions, and B-Tree indexing. Best used for high-concurrency production environments and standard relational data.

## Universal Features (Traits)

Regardless of which engine you choose, SPP_XDB guarantees the availability of these powerful framework-level features through shared PHP Traits:

*   **Encryption (`XDB_Encryption`)**: Field-level AES-256-GCM encryption with PBKDF2 key derivation. Transparently encrypts data before hitting the disk.
*   **Access Control (`XDB_Acl`)**: Granular read/write permissions at the database and table level using `acl.json`.
*   **Clustering (`XDB_Raft`)**: Node state tracking and remote RPC polling to allow eventual consistency synchronization across servers.
*   **Schema Validation (`XDB_Validator`)**: Strict schema enforcement using `[tableName].schema.json` to validate required fields, types (int, float, email), and lengths before writing.

## Advanced Features

### Bi-Directional Migration
A utility class `SPP_XDB_Migrator` is provided to seamlessly stream data between engines without lock-in.
```php
use SPPMod\SPPXDB\SPP_XDB_Migrator;
// Converts an entire XML table into an SQLite table
SPP_XDB_Migrator::migrateXmlToSqlite('my_database', 'users');
```

### Intelligent Query Caching
The Proxy Facade integrates natively with `\SPP\Cache`.
*   When `sys:db.cache_enabled` is true, all `querySQL()` reads are served instantly from Redis or FileCache.
*   The Facade utilizes **Tag-Based Invalidation**: performing an `insert`, `update`, or `delete` automatically purges the specific table's cache tags, guaranteeing zero stale reads while maintaining massive performance gains.

### Query Profiler & Logger
SPP_XDB features a built-in diagnostic tracer. 
```php
\SPPMod\SPPXDB\SPP_XDB::enableQueryLog();
// ... run queries ...
print_r(\SPPMod\SPPXDB\SPP_XDB::getQueryLog());
```
This logs the exact execution time (in milliseconds) of every underlying engine operation (`querySQL`, `insert`, etc.), helping you easily identify bottlenecks in production.

### Enterprise ORM Capabilities
`SPP_XDB` has evolved into a masterclass Object-Relational Mapper, supporting:

*   **Active Record Base Model (`SPP_XDB_Model`)**: Extend this class to define table mappings and attribute casting (`protected $casts = ['preferences' => 'array'];`).
*   **Relationships & Eager Loading**: Define relationships (`hasOne`, `hasMany`, `belongsTo`) and use `$qb->with('posts')` to resolve the N+1 query problem through intelligent data chunking.
*   **Query Scopes**: Define reusable model logic (e.g., `scopeActive($query)`) and chain them fluently on the Query Builder: `$db->asObject(User::class)->active()->get();`.
*   **Lifecycle Observers**: Tap into global CRUD lifecycle events. Register an Observer class to automatically trigger logic on `creating`, `created`, `updating`, `updated`, `deleting`, or `deleted`.
*   **Advanced Querying**: 
    - Full-text search groups: `$qb->search('keyword', ['title', 'content'])`
    - Seamless Pagination: `$paginator = $qb->paginate(15, 1);` returning a rich structure with `$total`, `$lastPage`, and `$data`.
*   **Soft Deletes**: Use `$qb->useSoftDeletes()` to gracefully hide archived records. Chain with `->withTrashed()` or `->onlyTrashed()` to query archived data.

### CLI Workflows (Migrations, Seeders, Factories)
SPP_XDB brings enterprise schema and testing workflows to the terminal via the `xdb-shell.php` interactive prompt:
*   **Migrations**: Run `MAKE:MIGRATION table` and `MIGRATE` to construct schemas incrementally and deterministically.
*   **Factories**: Define closures in `XDB_Factory::define()` to map random data patterns.
*   **Seeders**: Generate thousands of fake records instantly into test databases using `XDB_Factory::create('users', 500)` combined with `MAKE:SEEDER` and `SEED` shell commands.

## Code Structure

In Phase 4 of the engine's development, the massive "God Class" was decomposed into isolated Traits. 

*   `class.sppxdb.php`: The Proxy/Facade.
*   `class.querybuilder.php`: The fluent API builder (`$db->where()->get()`).
*   `Engines/`: Contains the concrete `XMLEngine` and `SQLiteEngine`.
*   `traits/`: Contains modular domain logic (`trait.crud.php`, `trait.encryption.php`, `trait.transactions.php`, etc.).

## Usage

```php
use SPPMod\SPPXDB\SPP_XDB;
use SPPMod\SPPXDB\QueryBuilder;

// Instantiation invokes the Facade
$db = new SPP_XDB('my_database');

// Fluent queries work on both engines identically
$qb = clone $db->connect('users');
$users = $qb->where('role', '=', 'admin')->orderBy('created_at', 'DESC')->limit(10)->get();

// Transactions
$db->beginTransaction();
try {
    $db->insert(['name' => 'Alice', 'role' => 'admin']);
    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
}
```

## Security

SPP_XDB has been rigorously audited and secured against:
1.  **Path Traversal**: Strict regex validation on database and table names.
2.  **XPath Injection**: Safe parameterized binding using nested `concat()` logic in the XML engine.
3.  **Concurrency Races**: Atomic file renaming (`rename()`) and strict `flock()` usage during multi-process writes.
4.  **Transaction Corruption**: Real-time DOM memory cloning preventing stale disk re-instantiations during global transactions.
