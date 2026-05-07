# SPP InterDB: Federated Data Aggregation & GraphQL Gateway

## 📖 Overview
**SPP InterDB** is an advanced data orchestration module designed to bridge multiple, heterogeneous database engines into a single, unified "Data Mesh." It allows developers to query data across MySQL, PostgreSQL, XDB, and other engines using a standardized GraphQL interface.

---

## 🏛️ The "InterDB" Architecture

The architecture is built on two core pillars:

### 1. The Universal Bridge (Adapter Pattern)
All database engines are treated as modular drivers that implement the `DBAdapter` interface. This decoupling allows the framework to interact with any storage backend—whether relational, hierarchical, or key-value—using a consistent API.

### 2. The Federated GraphQL Gateway
The gateway orchestrates queries across these adapters. It can parse a single GraphQL query, dispatch sub-queries to different databases, and "stitch" the results together into a cohesive response, resolving complex relationships in-memory.

---

## 🚀 Implementation Details

### The DBAdapter Interface
Every bridged database must implement these core operations:
```php
namespace SPPMod\SPPInterDB;

interface DBAdapter {
    public function query(string $sql, array $params = []): array;
    public function insert(string $table, array $data): bool;
    public function tableExists(string $table): bool;
    public function getSchema(string $table): array;
}
```

### The SPPInterDB Module
The central hub for data federation. It can operate in two modes:
- **`interdb`**: Full multi-database orchestration.
- **`standalone`**: Single-database GraphQL interface.

---

## 💻 Usage & Examples

### 1. Registering Mappings
Define which entities live in which database engine via configuration or code:
```php
use SPPMod\SPPInterDB\SPPInterDB;

$interDB = new SPPInterDB();

// 'user' is in the default MySQL DB, 'preferences' is in XDB
$interDB->map('user', 'default', 'users');
$interDB->map('preferences', 'xdb', 'user_preferences');
```

### 2. Executing Federated Queries
Fetch stitched data across multiple engines in a single request:
```php
$query = '
query {
  user(id: 1) {
    name
    email
    preferences {
      theme
      notifications
    }
  }
}';

$response = $interDB->graphql($query);
print_r($response['data']);
```

---

## 💼 Use Cases

### 1. Micro-Database Mesh
Instead of one massive database, split your data into specialized engines. Use **MySQL** for transactional data and **XDB** for flexible, evolving user metadata. The InterDB bridge makes them appear as one.

### 2. Legacy System Bridging
Modernize legacy databases (e.g. Oracle or Mainframe) by wrapping them in a `DBAdapter`. Use the **GraphQL Gateway** to serve legacy data alongside modern application data without complex migration projects.

### 3. Edge & Distributed Computing
Deploy **XDB** on edge nodes for local, low-latency processing. Use the **InterDB Bridge** to synchronize edge data back to a central **Core SQL** database for global reporting.

---

## 🛠️ Internal Mechanics: Relationship Stitching
When the Gateway encounters a nested field (e.g. `preferences` inside `user`), it:
1. Identifies the target engine for the nested field.
2. Extracts the foreign key from the parent result.
3. Dispatches a second query to the target engine.
4. Merges the nested result into the parent object before returning.

---
[Framework Index](index.md) | [SPPXDB Documentation](sppxdb.md)
