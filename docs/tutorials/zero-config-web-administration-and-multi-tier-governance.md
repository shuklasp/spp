# SPP Tutorial: Zero-Config Web Administration, Multi-Tier Governance & Hybrid Storage Architecture

Welcome to the beginner-friendly guide to SPPDocs enterprise administration. If you are brand new to the **SPP Framework** or web systems administration, this guide is designed for you. By the end of this tutorial, you will understand how SPPDocs allows you to manage entire developer portals, projects, modular features, user accounts, and database storage backends entirely through a graphical web interface—**without ever having to open or edit a single YAML or JSON configuration file by hand.**

---

## Table of Contents
1. [The Philosophy of "Zero-Config" Web Administration](#1-the-philosophy-of-zero-config-web-administration)
2. [Why Editing Config Files in Production is Risky](#2-why-editing-config-files-in-production-is-risky)
3. [Multi-Tier Governance & Authority Hierarchy](#3-multi-tier-governance--authority-hierarchy)
   - [Global Super Administrator](#global-super-administrator)
   - [Project Administrator](#project-administrator)
   - [Granular Member Roles](#granular-member-roles)
   - [Fail-Safe Lockout Guard](#fail-safe-lockout-guard)
4. [Architecture & System Flow](#4-architecture--system-flow)
   - [PermissionManager](#permissionmanager)
   - [UserService](#userservice)
   - [AdminController](#admincontroller)
   - [Strict CSS Separation Architecture](#strict-css-separation-architecture)
5. [Hybrid Storage Architecture & Live Zero-Loss Migration](#5-hybrid-storage-architecture--live-zero-loss-migration)
   - [Flat-File JSON Storage](#flat-file-json-storage)
   - [Embedded SQLite WAL Engine](#embedded-sqlite-wal-engine)
   - [How the Live Migration Works](#how-the-live-migration-works)
6. [Step-by-Step Walkthrough](#6-step-by-step-walkthrough)
   - [Step 1: Logging into the Admin Suite](#step-1-logging-into-the-admin-suite)
   - [Step 2: Configuring Platform-Wide Defaults](#step-2-configuring-platform-wide-defaults)
   - [Step 3: Creating Users and Managing Global Admins](#step-3-creating-users-and-managing-global-admins)
   - [Step 4: Project Management: Features, Storage, Links, and Team](#step-4-project-management-features-storage-links-and-team)
7. [Troubleshooting & FAQs](#7-troubleshooting--faqs)

---

## 1. The Philosophy of "Zero-Config" Web Administration

In many legacy systems, configuring a web portal requires accessing the server via SSH, opening text files in terminal editors like `nano` or `vim`, and manually modifying YAML, INI, or JSON files.

**SPPDocs Zero-Config** changes this paradigm:
- **No manual configuration files**: Every single platform parameter, feature flag, branding logo, storage engine, and access rule can be configured from a secure web dashboard.
- **Immediate atomic updates**: When an administrator updates a setting through the browser, SPPDocs parses, validates, sanitizes, and atomically writes the configuration to disk.
- **Fail-safe validation**: The system prevents invalid inputs, syntax errors, and fatal lockouts before anything is saved.

---

## 2. Why Editing Config Files in Production is Risky

If you have ever accidentally used spaces instead of tabs (or tabs instead of spaces) in a YAML file, you know that a single typo can bring down an entire web application.

Here is why manual file editing in production is dangerous:
1. **YAML Indentation Fragility**: YAML syntax is whitespace-sensitive. An extra space at the wrong column will crash the YAML parser and result in a 500 server error.
2. **Concurrent Overwrite Races**: If two administrators edit `project.yml` simultaneously via SSH or FTP, the second save will overwrite the first save without warning.
3. **File Permission & Ownership Drift**: Saving files as `root` can change file ownership, preventing the web server process (`www-data` or `apache`) from reading or updating them later.
4. **No Input Sanitization**: A human might enter `github.com/org/repo` without an `https://` prefix, causing web browsers to treat the link as an internal relative path (`http://localhost/school1/sppdocs/github.com/...`).

By enforcing a web-driven administrative interface, SPPDocs eliminates all four dangers completely.

---

## 3. Multi-Tier Governance & Authority Hierarchy

SPPDocs introduces a two-tiered administrative model that balances platform-wide governance with project-level autonomy.

```
                  ┌─────────────────────────────────────┐
                  │      Global Super Administrator     │
                  │   - Platform Branding & Logo        │
                  │   - Auth Mode & Storage Defaults    │
                  │   - User Directory & Promotion      │
                  │   - Unrestricted Cross-Project Full │
                  └──────────────────┬──────────────────┘
                                     │
           ┌─────────────────────────┴─────────────────────────┐
           ▼                                                   ▼
┌─────────────────────────────┐                     ┌─────────────────────────────┐
│    Project A: Demo App      │                     │    Project B: Mobile SDK    │
│  - Project Administrator    │                     │  - Project Administrator    │
│  - Maintainers              │                     │  - Maintainers              │
│  - Developers               │                     │  - Developers               │
│  - Reporters                │                     │  - Reporters                │
│  - Viewers                  │                     │  - Viewers                  │
└─────────────────────────────┘                     └─────────────────────────────┘
```

### Global Super Administrator
- **Scope**: Platform-wide across all managed projects.
- **Privileges**:
  - Access to `/admin/global-settings` (global portal title, default logo, default storage engine, default feature flags).
  - Access to `/admin/users` (view registered user directory, create accounts, grant or revoke Global Super Admin status).
  - Ability to create or delete entire projects.
  - Automatically possesses `admin` rights across every project on the portal.
- **Default Account**: The built-in `admin` user is automatically seeded as the initial Global Super Admin.

### Project Administrator
- **Scope**: Specific to an individual documentation repository and issue workspace (e.g., `demo-app`).
- **Privileges**:
  - Access to `/admin/project/{projectId}`.
  - General branding: Project title, motto, description, logo upload.
  - Modular feature toggles: Enable or disable Docs, Issue Tracker, Milestones, PM Dashboard, Community Forums, and Blog.
  - Storage Engine: View active engine (JSON or SQLite) and trigger live zero-loss data migration.
  - External Resources: Configure official website, source code repository, and community links.
  - Team RBAC: Assign project-specific roles to registered users.
  - Releases: Publish new release versions, download links, and changelog notes.

### Granular Member Roles
Within each project, users can be assigned one of the following roles:
- **`admin`**: Full project administration (can modify project settings, storage, and team roles).
- **`maintainer`**: Content and triage authority (can edit documentation pages, delete issues, manage milestones, and configure CI/CD automations).
- **`developer`**: Day-to-day contributor (can create issues, move cards on the Kanban board, comment, and log hours).
- **`reporter`**: Community reporter (can view docs and submit new bug reports or feature requests).
- **`viewer`**: Read-only stakeholder (can view published pages, issues, and discussions).

### Fail-Safe Lockout Guard
To prevent accidental administrative lockout, the system enforces a strict programmatic rule:
> **You cannot revoke the last remaining Global Super Admin.**

If an administrator attempts to revoke the final Global Super Admin account, `UserService::toggleGlobalAdmin()` intercepts the request, blocks execution, and returns an error message:
```php
if (empty($remaining)) {
    return ['success' => false, 'message' => 'Cannot revoke the last remaining Global Super Admin.'];
}
```

---

## 4. Architecture & System Flow

The zero-config administration suite is composed of four core pillars:

### PermissionManager
Located at [`src/SPPDocs/Services/PermissionManager.php`](file:///C:/projects/apache/school1/src/SPPDocs/Services/PermissionManager.php), this service provides static helper methods for role evaluation:
- `PermissionManager::getGlobalAdmins()`: Reads `src/SPPDocs/etc/sppdocs.yml` and returns an array of global admin usernames.
- `PermissionManager::isGlobalAdmin($username)`: Determines if the current or specified user is a Global Super Admin.
- `PermissionManager::isProjectAdmin($projectId, $username)`: Checks if the user is either a Global Super Admin or explicitly assigned the `admin` role in the project's `{issuesDir}/roles.json`.
- `PermissionManager::getProjectMembers($projectId)`: Returns all team members and their active roles for a given project.
- `PermissionManager::saveProjectMembers($projectId, array $roles)`: Atomically updates `{issuesDir}/roles.json`.

### UserService
Located at [`src/SPPDocs/Services/UserService.php`](file:///C:/projects/apache/school1/src/SPPDocs/Services/UserService.php), this service aggregates user accounts across multiple storage engines:
- Discovers users from the SPPXDB polyglot database table (`auth.users`) and the XML user store.
- Guarantees that the seeded `admin` account is always present and marked as a Global Super Admin.
- Handles user creation with secure one-way bcrypt password hashing (`password_hash($password, PASSWORD_DEFAULT)`).
- Enforces the fail-safe rule when promoting or revoking global admin authority.

### AdminController
Located at [`src/SPPDocs/Controllers/AdminController.php`](file:///C:/projects/apache/school1/src/SPPDocs/Controllers/AdminController.php), this controller extends `ViewController` and uses `ProjectAwareTrait`.
Every administrative action enforces:
1. **Authentication Check**: Verifies that a valid session exists.
2. **Authority Enforcement**: Calls `$this->requireGlobalAdmin()` or `$this->requireProjectAdmin($projectId)`.
3. **CSRF Token Verification**: Ensures that all incoming POST requests contain a valid cryptographic token.
4. **Atomic Yaml Serialization**: Reads the existing configuration file, merges changes, and writes back using `Yaml::dump()`.

### Strict CSS Separation Architecture
To adhere to enterprise code quality standards, **zero inline `<style>` tags exist in any Blade template files**.
All styling for cards, responsive grids, toggle switches, tables, badges, modals, and tab navigation is centralized in [`src/SPPDocs/resources/css/admin.css`](file:///C:/projects/apache/school1/src/SPPDocs/resources/css/admin.css).
This guarantees:
- **Zero Style Duplication**: The same utility classes (`.adm-card`, `.adm-btn`, `.adm-badge`) are shared across all pages.
- **Fast Browser Caching**: Browsers download `admin.css` once and cache it, reducing page weight and load times.
- **CSP Compliance**: Eliminates unsafe-inline Content Security Policy violations.

---

## 5. Hybrid Storage Architecture & Live Zero-Loss Migration

SPPDocs supports two high-performance storage backends for issues, comments, and milestones:

| Feature | Flat-File JSON Storage | Embedded SQLite WAL Engine |
| :--- | :--- | :--- |
| **Data Format** | Human-readable `.json` files + `index.json` | Single embedded `.sqlite` database file |
| **Dependencies** | Zero (Native PHP) | Zero (PHP PDO SQLite extension) |
| **Performance** | Ideal for projects under 1,000 issues | Microsecond queries, B-Tree indexed for 50,000+ issues |
| **Portability** | High (can commit directly into Git) | High (single file, easily backupable) |
| **Transactions** | File locking (`flock`) | ACID transactions with Write-Ahead Logging (`WAL`) |

### How the Live Migration Works
When an administrator selects a new storage engine in **Tab 3: Storage Engine** and clicks **Apply Storage Engine & Migrate Data**, the following automated sequence executes:

```
[Web Admin Form Submit]
          │
          ▼
[AdminController::saveStorage()]
          │
          ├─► Enforces $this->requireProjectAdmin($projectId)
          ├─► Verifies CSRF Token
          │
          ├─► Compares current engine vs target engine
          │
   ┌──────┴────────────────────────────────────────────────┐
   ▼                                                       ▼
[Target: SQLite]                                   [Target: JSON]
  1. Instantiate JsonStorageDriver                   1. Instantiate SqliteStorageDriver
  2. Instantiate SqliteStorageDriver                 2. Instantiate JsonStorageDriver
  3. Load all issues from JSON                       3. Load all issues from SQLite
  4. For each issue, save to SQLite with             4. For each issue, save to JSON with
     automatic unique number conflict resolution        full comments & time logs
  5. Update project.yml (storage: sqlite)            5. Update project.yml (storage: json)
   │                                                       │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼
              [302 Redirect to Project Settings]
              [Page reloads showing updated active engine badge]
```

---

## 6. Step-by-Step Walkthrough

### Step 1: Logging into the Admin Suite
1. Open your browser and navigate to:
   ```
   http://localhost/school1/sppdocs/admin
   ```
2. If you are not yet authenticated, you will be redirected to the login page.
3. Sign in using the default credentials:
   - **Username**: `admin`
   - **Password**: `admin` (or `admin123`)
4. Upon successful login, you will arrive at the **Projects Control Center** (`/admin`).

### Step 2: Configuring Platform-Wide Defaults
1. In the top navigation bar or sidebar, click **Platform Administration &rarr; Global Platform Settings** (`/admin/global-settings`).
2. Here you can configure:
   - **Platform Portal Title**: e.g., `Acme Corp Developer Portal`
   - **Global Logo Path**: `/school1/sppdocs/assets/logo.jpg`
   - **Global Auth Mode**: Choose between integrated `SPPAuth` or portable `Flat-File Session`.
   - **Default Project Storage Backend**: Choose whether newly created projects default to `JSON` or `SQLite`.
   - **Platform Default Feature Flags**: Toggle which modules are enabled out-of-the-box (Docs, Issues, Milestones, PM Dashboard, Forums, Webhooks).
3. Click **Save Global Platform Settings**. The settings are saved immediately to `src/SPPDocs/etc/sppdocs.yml`.

### Step 3: Creating Users and Managing Global Admins
1. In the sidebar, click **User & Access Governance** (`/admin/users`).
2. You will see a list of all registered accounts on the system, showing their username, email, authority level, and creation date.
3. To add a team member:
   - Click **➕ Create New User**.
   - Fill in the **Username** (e.g. `jdoe`), **Display Name**, **Email**, and **Password**.
   - Optionally check **Grant Global Super Administrator Rights** if this user should have master privileges.
   - Click **Create Account**.
4. To promote or revoke an existing user's global admin status:
   - Click **Promote to Global Admin** or **Revoke Global Admin** on their table row.
   - The system validates the fail-safe guard and updates the configuration.

### Step 4: Project Management: Features, Storage, Links, and Team
1. Return to the dashboard and click on any project (e.g. `My Demo App`).
2. The 7-tab interface provides complete control over the project:
   - **⚙️ General & Branding**: Edit the project title, motto, description, default documentation version (`v1`), and upload a custom logo.
   - **🧩 Modular Features**: Toggle Docs, Issues, Milestones, PM Dashboard, Community Forums, and Blog. For Issues and Forums, choose between Native Internal drivers or External outsourced drivers.
   - **🗄️ Storage Engine (Live Migration)**: Switch between Flat-File JSON and Embedded SQLite with a single click.
   - **🔗 External Links & Repo**: Manage both primary and arbitrary outbound web resources:
      - **Primary Resource Endpoints**: Configure official website, source code repository, community forum, and issue tracker URLs.
      - **Arbitrary Custom External Links**: Add unlimited custom links (such as Discord server, Slack workspace, Docker Hub images, NPM packages, or API references). Each custom link accepts an icon (emoji or symbol, e.g., 💬, ⚡, 📦, 🐳), a human-readable title, and a destination URL.
      - **Dynamic Frontend Integration**: Custom links are automatically rendered across the public project interface:
        - **Public Header / Top Bar**: Key external resources appear directly in the top navigation bar with icons and titles.
        - **Sidebar Navigation**: Rendered in the "Modules & Navigation" section with dedicated external indicators (`↗`).
        - **Project Home Page**: Displayed in the "External Resources" section with customized icons and hover transitions.
      - **Automatic Scheme Normalization**: Links missing protocol schemes (e.g. `discord.gg/example` or `github.com/org/repo`) are automatically normalized to `https://` by the `\SPP\Core\Url::external()` engine, preventing browser relative URL routing errors.
      - **Zero Inline HTML Architecture**: Dynamic row additions in the Admin Suite utilize native HTML `<template>` cloning, strictly eliminating inline HTML string literals in JavaScript components.
   - **👥 Team & RBAC Rights**: Select any registered user from the dropdown, choose their role (`Project Admin`, `Maintainer`, `Developer`, `Reporter`, `Viewer`), and click **➕ Add to Project**.
   - **📦 Releases & Downloads**: Add published release tags, download links, and changelog notes.
   - **⚠️ Danger Zone**: Global admins can permanently deregister and archive the project.

---

## 7. Troubleshooting & FAQs

### Q: Why do I get a 403 Forbidden error when attempting to access `/admin/global-settings`?
**A**: Only Global Super Administrators can access platform-wide settings. If your user is logged in as a standard user or project administrator, global platform routes are locked. Ensure your username is listed in `global_admins` in `sppdocs.yml`.

### Q: What happens to existing issues when I switch from JSON to SQLite?
**A**: Every single issue, title, description, comment, label, assignee, time tracking log, and timestamp is read from the JSON files and copied into the SQLite database. If any issue numbers collide from legacy JSON files, the driver automatically resolves the collision by assigning the next sequential number.

### Q: Why is there no CSS inside the Blade view files?
**A**: To comply with framework architectural rules, all styling is maintained in `src/SPPDocs/resources/css/admin.css`. This prevents style duplication, improves page caching performance, and keeps templates clean and readable.

---

*Authored for the SPP Framework Documentation Suite &bull; Updated September 2026*
