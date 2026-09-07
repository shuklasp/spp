# Novice Guide: SPP Native Multi-Role RBAC & Flat-File Permission Unions

Welcome to the definitive, beginner-friendly guide to Role-Based Access Control (RBAC) in SPPDocs and the SPP Framework.

Even if you have never configured an access control system or written enterprise PHP before, this guide will walk you through everything from the ground up: what RBAC is, how multiple roles work simultaneously on a single project, why **zero database configuration** is required, and how to govern users and permissions with confidence.

---

## 1. Foundational Concepts

### What is RBAC?
**Role-Based Access Control (RBAC)** is a security approach where permissions are assigned to **roles** (such as `Maintainer`, `Developer`, or `QA Lead`), and users are assigned to those roles. Rather than managing 50 individual checkboxes for every single user on your team, you simply assign them the appropriate role.

### The Single-Role Problem vs. Multi-Role Reality
In simple tools, a user can only have **one** role per project:
- *Problem*: What if `@satya` is a **Developer** who writes code, but also acts as the **Release Manager** for the project?
- In a single-role system, the administrator must either:
  1. Over-privilege the user by granting full root `Admin` rights, OR
  2. Under-privilege the user, forcing them to ask someone else to publish releases.
- *Solution*: **Multi-Role RBAC**. A user can hold multiple roles simultaneously on the same project (e.g., `Developer` + `Maintainer` + `QA Lead`).

### Do You Need a Database for SPP RBAC?
**No! A database is completely optional.**
While traditional frameworks (like Laravel or Django) require MySQL/PostgreSQL migrations and database pivot tables (`user_roles`), SPPDocs implements the **SPP RBAC Architecture directly on portable, flat-file JSON and YAML stores**:
1. **Zero Database Credentials Required**: Works out of the box in air-gapped environments, lightweight Docker containers, or standard Apache/PHP hosting without MySQL configuration.
2. **Git-Trackable & Truly Portable**: Project access control lives in `docs/{projectId}/issues/roles.json`. When you clone, branch, or back up a documentation repository, its role assignments travel with it.
3. **Sub-Millisecond In-Memory Evaluation**: Evaluated instantaneously without network latency or database query overhead.

---

## 2. Architecture & The Cumulative Permission Union

SPP's RBAC engine operates on the mathematical principle of a **Cumulative Distinct Union**:

```
┌────────────────────────────────────────────────────────────────────────┐
│               User: @satya on Project: 'core-api'                      │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
       ┌────────────────────────────┼────────────────────────────┐
       ▼                            ▼                            ▼
Role: Maintainer             Role: Developer              Role: QA Lead
  ['docs.edit',                ['issues.create',            ['issues.read',
   'docs.publish',              'issues.comment',            'releases.read',
   'webhooks.manage',           'issues.log_time']           'releases.manage']
   'issues.delete']                 │                            │
       │                            │                            │
       └────────────────────────────┼────────────────────────────┘
                                    │
                                    ▼
           Cumulative Permission Union (Effective Capabilities):
           - docs.edit          (from Maintainer)
           - docs.publish       (from Maintainer)
           - webhooks.manage    (from Maintainer)
           - issues.delete      (from Maintainer)
           - issues.create      (from Developer)
           - issues.comment     (from Developer)
           - issues.log_time    (from Developer)
           - releases.read      (from QA Lead)
           - releases.manage    (from QA Lead)
```

### Rule of Permission Union:
If **ANY** of the user's assigned roles possesses a permission, the user is **authorized**. Access is only denied if **NONE** of their assigned roles grant the capability.

---

## 3. Storage Blueprint: What Lives Where

SPPDocs organizes its flat-file access governance cleanly across three distinct layers:

| Layer | File Path | Format | Purpose |
| :--- | :--- | :--- | :--- |
| **Role Catalog** | `src/SPPDocs/etc/roles.json` | JSON | Defines all available system roles and custom roles, with their respective permissions lists. |
| **Project Role Assignments** | `docs/{projectId}/issues/roles.json` | JSON | Maps individual usernames to an array of assigned roles for that project. |
| **Platform Governance** | `src/SPPDocs/etc/sppdocs.yml` | YAML | Defines platform-wide root `global_admins` and portal defaults. |

### Example `docs/{projectId}/issues/roles.json` File:
```json
{
  "satya": [
    "maintainer",
    "developer"
  ],
  "jane_smith": [
    "developer",
    "qa_lead"
  ],
  "guest_reviewer": [
    "viewer"
  ]
}
```

> [!NOTE]
> **100% Backward Compatible**:
> If an existing project has legacy scalar string assignments (e.g. `"satya": "developer"`), the SPPDocs loader automatically normalizes it into `["developer"]` upon read and write. No manual data migration is required.

---

## 4. How to Manage Roles via the Admin UI

1. Navigate to the **User Directory** at `http://localhost/school1/sppdocs/admin/users`.
2. Locate the user you wish to configure (e.g., `@satya`).
3. Click the **"🛡️ Assign Roles"** button.
4. The **Manage Project Role Assignments** modal appears:
   - Every documentation project registered on your platform is listed with its project title and identifier badge.
   - For each project, you will see interactive, color-coded role tags:
     - <span style="color:#b91c1c; font-weight:bold;">Admin</span>: Universal project access.
     - <span style="color:#1d4ed8; font-weight:bold;">Maintainer</span>: Content editing, sprint triage, webhooks, and issue deletions.
     - <span style="color:#15803d; font-weight:bold;">Developer</span>: Active contributions, task logging, and discussions.
     - <span style="color:#b45309; font-weight:bold;">Reporter</span>: Community bug reporter.
     - <span style="color:#475569; font-weight:bold;">Viewer</span>: Read-only observer.
     - <span style="color:#7e22ce; font-weight:bold;">✨ Custom Roles</span>: Any domain-specific custom roles created by your organization.
5. Click on any role tag to toggle it **on** (it illuminates with a checkmark `✓` and colored background) or **off**.
6. Use the **"✕ Clear Roles"** button next to any project to quickly revoke all access for that project.
7. Click **"💾 Save Role Assignments"**.
8. Changes take effect immediately! In the user directory table, the user's assigned roles appear grouped in clean project clusters:
   `core-api [Maintainer] [Developer]`

---

## 5. Developer Code Walkthrough

When developing new features, controllers, or API endpoints in SPPDocs, evaluating permissions is effortless:

### Checking Permissions in PHP Controllers (`canUser`)
```php
use App\SPPDocs\Services\PermissionManager;

// Check if a user can delete an issue in project 'core-api'
$canDelete = PermissionManager::canUser('issues.delete', 'core-api', 'satya');

if (!$canDelete) {
    http_response_code(403);
    exit("Access Denied: You lack 'issues.delete' permission.");
}
```

### Checking Permissions via `RBACGuard` Trait
Any controller utilizing `RBACGuard` automatically benefits from multi-role evaluation:
```php
class IssueController extends \SPPMod\SPPView\ViewController
{
    use \App\SPPDocs\Traits\RBACGuard;

    public function deleteIssue()
    {
        $user = $this->getProjectUser();
        
        // Evaluates against ALL roles assigned to the user in this project
        $this->authorizeAction($this->issuesDir, $user, 'delete');

        // Proceed with deletion...
    }
}
```

### Retrieving All Roles for a User
```php
// Returns array of role slugs: e.g. ['maintainer', 'developer']
$roles = PermissionManager::getProjectUserRoles('core-api', 'satya');

// Backward-compatible single role (resolves highest priority):
$primaryRole = PermissionManager::getProjectUserRole('core-api', 'satya'); // 'maintainer'
```

---

## 6. Summary of Architectural Advantages

- **Zero-Friction Operations**: No database migrations, SQL seeds, or foreign key deadlocks.
- **Full Parity with SPP Framework**: Uses the same M:N multi-role paradigm as `\SPPMod\SPPAuth\SPPUser` and `spp_userroles`.
- **Pure Self-Containment**: Fully compatible with air-gapped environments, static site exports, and Git version control.
