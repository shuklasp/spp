# SPP XDB: Enterprise-Grade Native XML Database Engine

## 📖 Overview
**SPP XDB** is a Tier-1 Native XML Database (NXD) engine integrated into the SPP Framework. It provides a robust, schema-agnostic storage solution that combines the hierarchical flexibility of XML with the rigorous integrity and scalability of enterprise relational databases.

## 🏛️ Architecture

### 1. Multi-Paradigm Query Layer
SPP XDB supports three distinct query languages, allowing developers to choose the best tool for the task:
*   **SQL-92**: A standard SQL interface (SELECT, INSERT, UPDATE, DELETE, JOIN, GROUP BY).
*   **GraphQL**: A modern API bridge that translates GQL queries into optimized database operations.
*   **FLWOR-Lite**: A native XQuery-inspired syntax for tree-based data navigation.

### 2. Extreme Scaling & Storage
*   **Horizontal Partitioning**: Automatically segments tables into multiple physical files (e.g., `data.0.xml`, `data.1.xml`) once row thresholds are reached.
*   **Transparent Compression**: Supports searching and reading GZip-compressed segments (`.xml.gz`) on-the-fly.
*   **BLOB Offloading**: Large binary data is stored in a dedicated `_blobs/` directory, keeping the primary XML metadata lean and fast.
*   **Materialized Views**: Caches complex query results to disk in JSON format for sub-millisecond retrieval.

### 3. Intelligence & Maintenance
*   **Cost-Based Optimizer (CBO)**: Analyzes table statistics to generate the most efficient execution plan (e.g., Index Scan vs. Full Scan).
*   **Self-Healing Engine**: Automatically detects and reports XML corruption, missing segments, or orphaned foreign key references.
*   **Autonomous Indexing**: Tracks slow queries and suggests optimal indexing strategies via the admin dashboard.

### 4. Security & Governance
*   **Blockchain Audit Chain**: Every data mutation is cryptographically linked to the previous entry, creating an immutable audit trail.
*   **Table-Level ACL**: Granular read/write permissions enforced at the engine core.
*   **Temporal Data (Time Travel)**: Native versioning allows querying the database state "As Of" any historical timestamp.
*   **Global ACID Transactions**: Journal-based atomic commits across multiple tables.

---

## 🚀 Implementation & Usage

### Basic Connection
```php
use SPPMod\SPPXDB\SPP_XDB;

$xdb = new SPP_XDB('my_database');
$xdb->connect('users');
```

### SQL Operations
```php
// Complex JOIN across partitioned segments
$sql = "SELECT users.name, orders.total 
        FROM users 
        INNER JOIN orders ON users.id = orders.user_id 
        WHERE orders.total > 500";
$results = $xdb->querySQL($sql);
```

### GraphQL Bridge
```php
$gql = '{ users(role: "admin") { id name email } }';
$data = $xdb->queryGraphQL($gql);
```

### SQL Introspection & DDL
The SQL engine supports standard database discovery and management commands:
```php
// List all databases
$databases = $xdb->querySQL("SHOW DATABASES");

// List tables in current database
$tables = $xdb->querySQL("SHOW TABLES");

// Describe table schema (Type, Null, Key, Default, Extra)
$schema = $xdb->querySQL("DESCRIBE users");
```

### Global Transactions (Tier-1 ACID)
```php
$xdb->beginGlobalTransaction();
try {
    $xdb->connect('accounts')->update(['balance' => 900], 'id = 1');
    $xdb->connect('ledger')->insert(['type' => 'DEBIT', 'amount' => 100]);
    $xdb->commitGlobal();
} catch (Exception $e) {
    $xdb->rollbackGlobal();
}
```

---

## 🔗 Framework Integration (SPPDB Driver)
SPPXDB is now fully integrated into the `SPPDB` abstraction layer. You can use XDB transparently via the generic database methods.

### Usage in SPPDB
```php
use SPPMod\SPPDB\SPPDB;

// Automatically uses XDB if dbtype: xdb is configured
$db = new SPPDB();

// Generic SELECT (engine-agnostic)
$results = $db->execute_query("SELECT * FROM users WHERE status = ?", ['active']);

// Fluent Query Builder
$user = $db->table('users')->where('id', 1)->first();
```

---

## 🐚 Interactive CLI Shell
The XDB Shell provides a high-performance REPL for direct database communication.

### Launching the Shell
```bash
# Standard launch via SPP CLI
php spp.php xdb:shell
```

### Features
- **Prompt**: `xdb(context)>` for visual feedback.
- **SQL Compatibility**: Full support for `SELECT`, `INSERT`, `UPDATE`, `DELETE`, and `SHOW`.
- **ASCII Tables**: Automatic terminal formatting for query results.
- **Quiet Mode**: Automatically suppresses framework discovery logs for a clean interface.

---

## 💼 Use Cases

1.  **Compliance-Heavy Systems**: High-security environments where an untamperable (Blockchain) audit trail is required.
2.  **Rapid Schema Prototyping**: Applications with evolving data structures where traditional SQL migrations are too rigid.
3.  **Distributed Content Management**: Large-scale CMS systems requiring horizontal partitioning and multi-node replication.
4.  **Legacy Data Modernization**: Using XSLT to transform complex XML data into modern JSON/HTML interfaces.
5.  **Historical Auditing**: Financial or legal systems needing "Time Travel" queries to view past database states.

---

## 🛠️ Internal Mechanics: The Raft Consensus
For high availability, SPP XDB implements a simplified **Raft protocol** for leader election and log replication.
*   **Follower**: Listens for heartbeats from the leader.
*   **Candidate**: Requests votes from other nodes to become a leader.
*   **Leader**: Manages all write operations and replicates the binlog to followers.

---
[Framework Index](index.md) | [Core Modules](modules/index.md)
