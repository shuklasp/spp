# Chapter 1 — Introduction to SPP

## 1.1 What is SPP?

Satya Portal Pack (SPP) is a modular PHP application framework designed for building large portal applications. Every feature is implemented as a module with its own manifest, configuration, views, controllers, and assets.

## 1.2 Core Architecture

- Bootstrap initializes the runtime.
- Scheduler creates the application context.
- Registry stores shared services.
- Module Loader discovers enabled modules.
- View Engine renders templates.

## 1.3 Directory Layout

```text
documentation/
modules/
system/
public/
config/
```

## 1.4 Request Lifecycle

1. HTTP request reaches the bootstrap.
2. Application context is created.
3. Enabled modules are loaded.
4. Routing selects the controller.
5. Controller prepares data.
6. View renders the final response.

This Markdown file is the canonical source for Chapter 1.
