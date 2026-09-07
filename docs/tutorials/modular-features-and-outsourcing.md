# Novice-First Guide: SPPDocs Modular Architecture, Presets & UI Decluttering

Welcome to the comprehensive architecture and user guide for **SPPDocs**. Whether you are building an open-source documentation site, an internal enterprise project hub, or a high-velocity developer portal, this guide will teach you everything from first principles — even if you have never used SPPDocs or the SPP framework before.

---

## 1. Foundational Concepts: Why Modular Features?

### The Clutter Problem
Traditional documentation platforms often suffer from two extremes:
1. **The Over-Cluttered Monolith**: Every conceivable feature (issue tracking, Kanban boards, milestones, forums, dynamic query builders, taxonomy managers, block placement engines, schema validators, OpenAPI explorers, internationalization editors) is permanently visible on every page. For a developer or reader who just wants clean, fast, distraction-free documentation, the interface is cluttered with irrelevant administrative panels, extraneous sidebar tabs, and bulky cards.
2. **The Rigid Minimalist**: Platforms that only support static markdown and completely lack the ability to grow into enterprise workflows, project management, or advanced content architecture when needed.

### The SPPDocs Solution
SPPDocs provides a **16-Feature Modular Architecture** divided into 4 clear functional tiers:
1. **Tier 1: Core Content & Publishing** (`docs`, `blog`, `book_export`)
2. **Tier 2: Project Management & Collaboration** (`issues`, `milestones`, `pm_dashboard`, `forums`)
3. **Tier 3: Content Architecture & Studio Tools** (`views`, `taxonomy`, `blocks`, `modules`, `schemas`, `media`)
4. **Tier 4: Developer & API Integration** (`openapi`, `i18n`, `webhooks`, `automations`)

Every feature can be toggled on or off with a single click in the Admin Console or a single boolean line in `project.yml`.

### One-Click Presets & Custom Presets
To make configuration effortless without manually toggling 16 checkboxes:
- **Built-in Presets**:
  - 📖 **Minimal Documentation**: Only docs, search, and publication export enabled. Zero CMS/studio clutter.
  - 🎯 **Docs & Project Management**: Adds Kanban sprint boards, milestones, time tracking, and velocity dashboards.
  - ⚡ **Developer & API Portal**: Adds interactive OpenAPI explorer, multi-language hub, webhooks, and CI/CD automations.
  - 🚀 **Full Enterprise Portal**: All 16 features active for full studio query builders, taxonomy trees, and extensible block layouts.
- **Custom Presets**: Create and save your own team-specific or organization-specific presets (stored in `src/SPPDocs/etc/feature_presets.yml`). Apply or delete custom presets with a single click.

---

## 2. The 16 Features Catalog

| Feature Identifier | Display Name | Functional Tier | Description |
| :--- | :--- | :--- | :--- |
| `docs` | Core Documentation | Tier 1: Content & Publishing | Markdown-powered hierarchical documentation reader with instant full-text search. *(Required)* |
| `blog` | Project Blog & News | Tier 1: Content & Publishing | Chronological articles, release announcements, and author frontmatter feeds. |
| `book_export` | Full-Book Print & PDF | Tier 1: Content & Publishing | Compile entire multi-page documentation trees into a single cohesive, printable document. |
| `issues` | Issue Tracker & Kanban | Tier 2: Project Management | Integrated bug tracking, sprint board, backlog triage, time logs, and labels. |
| `milestones` | Milestones & Roadmaps | Tier 2: Project Management | Sprint goal tracking, completion progress indicators, and release milestones. |
| `pm_dashboard` | PM Analytics & Velocity | Tier 2: Project Management | Project velocity charts, team workload distribution, and milestone burndown metrics. |
| `forums` | Community Forums | Tier 2: Collaboration | Threaded discussion boards, topic categories, and guest interaction governance. |
| `views` | Views Studio Query Engine | Tier 3: Content Architecture | Visual dynamic query builder across markdown documents with taxonomy filtering, card grids, and JSON feeds. |
| `taxonomy` | Taxonomy & Vocabularies | Tier 3: Content Architecture | Hierarchical category trees, term tags, colored badges, and automated term document archives. |
| `blocks` | Theme Regions & Blocks | Tier 3: Content Architecture | Pluggable layout engine placing views, widgets, or custom content into theme regions. |
| `modules` | App Modules & Extensions | Tier 3: Content Architecture | Modular plug-in architecture with kernel alter hooks (`document_render`, `before_save`, `sidebar_alter`). |
| `schemas` | Content Type Schemas | Tier 3: Content Architecture | Visual frontmatter schema governance, field type constraints, and edit permissions. |
| `media` | Media & Image Styles | Tier 3: Content Architecture | Media asset library with automated thumbnail, medium, and web derivative processing pipelines. |
| `openapi` | Interactive OpenAPI Explorer | Tier 4: Developer Integration | Interactive Swagger/Redoc explorer with live parameter testing against REST API endpoints. |
| `i18n` | Multi-Language (i18n) Hub | Tier 4: Developer Integration | Locale switcher, translation parity matrix, and automated language routing. |
| `webhooks` | Git & Outbox Webhooks | Tier 4: Developer Integration | Inbound Git sync triggers and outbound transactional HMAC-signed webhooks. |
| `automations` | CI/CD & PM Automations | Tier 4: Developer Integration | Trigger-action event rules automating issue assignments, status shifts, and label tagging. |

---

## 3. UI Decluttering Across All Touchpoints

When a feature is disabled for a project, SPPDocs completely cleans it from the user experience across all touchpoints:

### 1. Public Documentation Sidebar
- Regular documentation readers never see administrative studio links (`admin/views`, `admin/taxonomy`, `admin/schemas`, etc.).
- Navigation modules (`forums`, `issues`, `blog`, `book_export`) only appear if enabled.
- If Book Export is enabled, a clean download link appears under Modules & Navigation. If disabled, it leaves no trace.

### 2. Project Home Page (`/project/{projectId}`)
- Navigation cards for disabled features (Blog, Forums, Issue Tracker) are suppressed.
- The large "Content Architecture & Studio Tools" card grid is **completely hidden** for readers, and is hidden for administrators if all studio features are disabled.
- Only cards for enabled tools appear, maintaining a clean, focused reading experience.

### 3. Admin Console Sidebar (`/admin/...`)
- Project-scoped tabs (`media`, `webhooks`, `automations`, `schemas`, `openapi`, `i18n`, `views`, `taxonomy`, `modules`, `blocks`, `book_export`) are conditionally rendered via `FeatureManager::isEnabled($feature, $project)`.
- When using the **Minimal Documentation** preset, the admin sidebar drops from 18 links down to essential project settings and user access controls.

### 4. Controller-Level URL Guards
- Direct URL visits to disabled features (e.g., `/admin/views?project=demo-app` when views is disabled) do not produce 500 errors or confusing broken pages.
- For administrators, `FeatureManager::requireFeature()` intercepts the request and issues an immediate `HTTP 302` redirect to `admin/project/{projectId}?tab=tab-features` with a friendly flash notice:
  > *"The Dynamic Views Studio feature is currently disabled for project 'My Demo App'. Enable it in Project Features."*
- For public reader endpoints (`/issues`, `/forums`), a clean `HTTP 404 Not Found` is returned.

---

## 4. Step-by-Step Configuration Tutorials

### Tutorial A: Activating a Preset via Admin UI
1. Log in to the Admin Console at `/admin`.
2. Click on your project under **Projects Dashboard** or click **Project Settings & Features** in the sidebar.
3. Switch to the **Features & Drivers** tab (or navigate directly to `/admin/project/{projectId}?tab=tab-features`).
4. In the **Quick Configuration Presets** bar, click any preset button:
   - Click **📖 Minimal Documentation** to immediately switch all toggle switches off except Docs.
   - Click **🎯 Docs & Project Management** to enable issues, milestones, and PM dashboard.
   - Click **⚡ Developer & API Portal** to activate API explorer and webhooks.
5. Click **💾 Save Feature Configuration**. The project configuration is updated in `project.yml`.

### Tutorial B: Creating a Custom Feature Preset
1. On the **Features & Drivers** tab, toggle the checkboxes to your organization's exact requirements (for example, Docs + Blog + Taxonomy + Book Export).
2. Click the **➕ Save Current as Custom Preset** button in the preset bar.
3. In the modal dialog:
   - **Preset Name**: Enter a name (e.g., `Technical Writer Suite`).
   - **Identifier Key**: Enter a unique slug (e.g., `technical_writer_suite`).
   - **Icon**: Choose an emoji (e.g., `📝`).
   - **Description**: Enter an optional description.
4. Click **Save Custom Preset**.
5. Your custom preset is now saved into `src/SPPDocs/etc/feature_presets.yml` and appears immediately in the preset bar for all projects.
6. To delete a custom preset at any time, click the red **×** next to its name.

### Tutorial C: Configuring Features Directly in YAML
In your project's configuration file (`docs/{projectId}/project.yml`):

```yaml
title: "Clean Modern Docs"
default_version: v1
versions:
  v1: docs/clean-docs/pages

features:
  docs: true
  blog: false
  book_export: true
  issues:
    enabled: false
    driver: internal
  milestones: false
  pm_dashboard: false
  forums:
    enabled: false
    driver: internal
  views: false
  taxonomy: false
  blocks: false
  modules: false
  schemas: false
  media: true
  openapi: false
  i18n: false
  automations: false
  webhooks: false
```

---

## 5. Developer Architecture: Using FeatureManager in Code

When writing custom controllers, plugins, or extensions, always consult `FeatureManager`:

```php
use App\SPPDocs\Services\FeatureManager;

// 1. Check if a feature is enabled
if (FeatureManager::isEnabled('views', $project)) {
    // Render dynamic view displays...
}

// 2. Guard an admin action (redirects to settings tab if disabled)
FeatureManager::requireFeature('taxonomy', $project, $projectId);

// 3. Inspect registered presets
$presets = FeatureManager::getAllPresets();

// 4. Save custom presets programmatically
FeatureManager::saveCustomPreset('dev_ops_suite', [
    'title' => 'DevOps Suite',
    'icon' => '🔧',
    'description' => 'Automations, webhooks, and API explorer',
    'features' => [
        'docs' => true,
        'openapi' => true,
        'webhooks' => true,
        'automations' => true,
    ]
]);
```

With this architecture, SPPDocs remains ultra-lean by default while offering enterprise extensibility on demand!
