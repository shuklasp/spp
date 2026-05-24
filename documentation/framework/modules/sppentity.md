# Core Module: SppEntity (ORM)

SppEntity is the framework's high-performance Object-Relational Mapper (ORM). It provides a fluent, object-oriented interface for interacting with database records.

---

## 1. Basic Philosophy
SppEntity is built on the **Data Mapper / Active Record Hybrid** pattern. It aims to eliminate boilerplate SQL while providing maximum flexibility for complex relationships and performance optimizations.

---

## 2. Architecture
The module uses a **Schema-Driven** approach. Each entity is defined by its metadata (often in `module.yml` or PHP classes), which the framework uses to map database columns to object properties.

### Key Components:
*   **EntityManager**: The central registry for all active entities.
*   **EntityRelations**: Manages complex `belongsTo`, `hasMany`, and `manyToMany` relationships.
*   **Query Builder**: A fluent API for constructing complex SQL queries without writing raw SQL.

---

## 3. API & Usage

### Basic CRUD
```php
// Create
$user = new User();
$user->name = "Satya";
$user->save();

// Read
$user = User::find(1);

// Update
$user->email = "satya@spp.com";
$user->update();

// Delete
$user->delete();
```

### Relationship Usage
```php
// Accessing related entities
$posts = $user->getRelated('posts');

// Saving with relationships
$post = new Post(['title' => 'My First Post']);
$user->link('posts', $post);
```

---

## 4. Enterprise Features (Phase 6)

SPP Entity now natively supports enterprise-grade persistence and scaling features. You do not need to implement these in your app; the framework handles them automatically via entity metadata flags.

### 1. Dynamic JSON Flattening (`fields_data`)
If an entity supports dynamic or unstructured data, SPP flattens the internal `fields_data` JSON column automatically. You can retrieve and assign properties dynamically exactly as if they were columns:
```php
$user->custom_hobby = 'Painting'; // Automatically stored in fields_data
$user->update();
```

### 2. Multi-Lingual Translations
SPP Entity handles translations seamlessly at the framework layer. Use the `setLanguage()` method to seamlessly switch the active locale. Reads and writes will be diverted to the global `spp_entity_translations` table:
```php
$post->setLanguage('fr');
$post->title = 'Mon Premier Article';
$post->update(); // Saves 'fr' translation cleanly without wiping English 'title'
```

### 3. Historical Revisions (Delta Tracking)
By specifying `track_revisions: true` in your entity's `module.yml`, the framework calculates diff deltas automatically on `$entity->update()`. These audit logs are persistently stored in the global `spp_entity_revisions` table with timestamps and author IDs.

### 4. Edge Caching & Cache Tags
Read queries (`loadFiltered()`) automatically emit granular HTTP `X-SPP-Cache-Tags` mapped to the underlying query structure (e.g. `User_list`). Writing to or saving an entity automatically triggers invalidation calls via `\SPPMod\SPPCache\SPPCacheManager`, seamlessly purging outdated CDNs or reverse proxies without affecting unrelated data.

---

## 5. Performance Features
*   **Lazy Loading**: Related entities are only fetched from the database when actually accessed.
*   **Eager Loading**: Support for `with()` to prevent N+1 query problems.
*   **Identity Map**: Ensures that the same database record is represented by the same object instance throughout the request.

---
[Back to Modules Index](index.md)
