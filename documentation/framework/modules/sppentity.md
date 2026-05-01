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

## 4. Performance Features
*   **Lazy Loading**: Related entities are only fetched from the database when actually accessed.
*   **Eager Loading**: Support for `with()` to prevent N+1 query problems.
*   **Identity Map**: Ensures that the same database record is represented by the same object instance throughout the request.

---
[Back to Modules Index](index.md)
