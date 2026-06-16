# SPP Framework Tutorial

Welcome to the official tutorial for the **Satya Portal Pack (SPP) Framework**. This guide is designed to take you from a beginner to a pro in building modular, event-driven web applications using SPP.

## Table of Contents

1.  [**Introduction**](01_introduction.md)
    - What is SPP?
    - Core Philosophy
    - Key Features
2.  [**Getting Started**](02_getting_started.md)
    - Directory Structure
    - The CLI Tool (`spp.php`)
    - Initializing an App
3.  [**Modular Architecture**](03_modules.md)
    - Module Containers
    - `module.xml` Configuration
    - Creating Your First Module
4.  [**Data Modeling with YAML Entities**](04_data_models.md)
    - Designing Entities in YAML
    - Using `SPPEntity` for ORM
    - Automated Schema Management
5.  [**Routing and Views**](05_routing_and_views.md)
    - Defining Routes in `pages.yml`
    - The `ViewPage` Rendering Engine
    - SPA "Drop and Play"
6.  [**Forms & Validation**](06_forms_and_validation.md)
    - YAML/XML Form Definitions
    - Automatic Form Augmentation
    - Client and Server-side Validation
7.  [**The Event System**](07_events.md)
    - Understanding Hooks
    - Registering and Firing Events
    - Extending Core Functionality
8.  [**Advanced Features**](08_advanced.md)
    - `SPPAuth`: Authentication and Roles
    - `SPPLogger`: Flexible Logging
    - `SPPAI` & `SPPLive`: Vector ORM and WebSockets
    - `SPPConfig`: .env & YAML Configurations
9.  [**Project: Building SPP-Twitter**](09_project_twitter.md)
    - Putting it all together
    - A real-world clone
10. [**Business Intelligence & Reporting**](10_advanced_reporting.md)
    - Zero-dependency dynamic reporting
    - WYSIWYG Print Templates
11. [**Multi-Engine View Rendering**](11_multi_engine_routing.md)
    - Twig and Blade Support
    - ViewCompiler (AST Engine)
12. [**Live Components (Livewire Clone)**](12_live_components.md)
    - Reactive State Hydration
    - WebSockets & AJAX Fallback
13. [**Security Hardening**](13_security_hardening.md)
    - The Brutal Audit Defenses
    - SQLi, LFI, and RCE Protections
14. [**Project: Blogging Platform**](14_blogging_platform.md)
    - SPPView Paradigm Implementation
    - Drishyam Paradigm Implementation
    - SPPUX Paradigm Implementation

---

> [!TIP]
> If you are new to the framework, start with the [Introduction](01_introduction.md) to understand the architectural flow.
