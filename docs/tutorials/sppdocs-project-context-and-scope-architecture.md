# Novice Guide: SPPDocs Project Context & Scope Architecture

Welcome to the beginner-friendly guide to **Project Context & Scope Architecture** in SPPDocs.

Even if you are completely new to SPP or multi-tenant web applications, this guide explains how SPPDocs guarantees that administrators always know **which project settings and user roles are being applied**, eliminating ambiguity and accidental misconfigurations across documentation projects.

---

## 1. Foundational Concepts

### The "Ambiguity Trap" in Multi-Project Portals
When an organization manages multiple documentation repositories (for example, `core-api`, `frontend-sdk`, and `demo-app`), an administrator frequently moves between project settings, CI/CD automations, webhooks, media libraries, and team permissions.

In naive admin panels, a critical flaw emerges:
- An administrator clicks on **"Roles & Permissions"** from within a project's settings.
- The browser opens `/admin/roles`.
- The screen displays a generic list of roles (`Maintainer`, `Developer`, `Reporter`).
- The administrator clicks **"+ Assign User"**, and is greeted with a generic dropdown: `-- Choose Project --`.
- **The Ambiguity Trap**: *"Which project am I assigning this user to? Did my click from 'My Demo App' carry over, or am I about to grant permissions on the wrong repository?"*

### The SPPDocs Solution: Explicit Scoped Architecture
To solve this, SPPDocs enforces an **Explicit Project Context Pattern** across all administration pages:
1. **Never Make the User Guess**: Every page that operates on or filters by a project must visibly and prominently declare the active project name (`My Demo App`) and unique identifier (`demo-app`).
2. **Context-Aware Layout Header**: When a project is active, the global navbar displays an active project pill (`📁 Project: My Demo App (demo-app)`) with a live status indicator.
3. **Breadcrumb Context Strip**: A dedicated trail (`Platform / 📁 My Demo App (demo-app) / Roles & Permissions`) anchors every view, providing one-click access to the live docs or project switcher.
4. **Pre-Bound Modals & Sticky State**: Opening an assignment or configuration modal pre-selects the active project, displays a target scope banner, and automatically returns the administrator back to that project's context upon submission.

---

## 2. Architecture & Data Flow

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    User Navigates with Project Scope:                        │
│            GET /admin/roles?projectId=demo-app                               │
└──────────────────────────────────────┬───────────────────────────────────────┘
                                       │
                                       ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                AdminPMController::rolesIndex() Resolution                    │
│   1. Extracts $_GET['projectId'] ('demo-app')                                │
│   2. Loads project metadata: Title: 'My Demo App', ID: 'demo-app'             │
│   3. Calculates:                                                             │
│      - $role_usage: Total members platform-wide                              │
│      - $role_project_usage: Members holding role in 'demo-app'               │
│   4. Passes 'project_id' and 'project' to View & Layout                      │
└──────────────────────────────────────┬───────────────────────────────────────┘
                                       │
        ┌──────────────────────────────┴──────────────────────────────┐
        ▼                                                             ▼
┌───────────────────────────────────┐               ┌───────────────────────────────────┐
│     layouts/admin.blade.php       │               │      admin/roles.blade.php        │
├───────────────────────────────────┤               ├───────────────────────────────────┤
│ • Top Header:                     │               │ • Scope Banner:                   │
│   📁 Project: My Demo App         │               │   ACTIVE PROJECT SCOPE            │
│ • Project Navigation Sidebar:     │               │   My Demo App (demo-app)          │
│   Full project menu enabled       │               │ • Table Badges:                   │
│ • Context Breadcrumb Strip:       │               │   📁 2 in project  👥 3 total     │
│   Platform / 📁 demo-app / RBAC   │               │ • + Assign User Modal:            │
│   [📖 Live Docs] [⇄ Switch Proj]  │               │   Pre-selected: 'My Demo App'     │
└───────────────────────────────────┘               │   Hidden: return_project_id       │
                                                    └───────────────────────────────────┘
```

---

## 3. Detailed Component Breakdown

### 1. The Global Header Active Project Pill
Located in `src/SPPDocs/resources/views/layouts/admin.blade.php`:
- Automatically triggers whenever `$project_id` is supplied to the layout.
- Renders:
  ```html
  <div class="adm-header-active-project" title="Active Documentation Project Scope">
      <span class="adm-project-dot"></span>
      <span class="adm-header-proj-prefix">Project:</span>
      <strong class="adm-header-proj-title">My Demo App</strong>
      <code class="adm-header-proj-code">demo-app</code>
  </div>
  ```
- Uses `.adm-project-dot` with a glowing emerald pulse to confirm that the session is focused on a specific project.

### 2. The Project Context Strip & Breadcrumbs
Rendered right at the top of `#adm-main-content` before alerts and page contents:
- Shows the full hierarchical path:
  `Platform / 📁 My Demo App (demo-app) / 🛡️ Team Members & RBAC`
- Contains quick utility links:
  - `📖 Live Docs →`: Opens the live generated documentation in a new tab.
  - `⇄ Switch Project`: Navigates back to the root projects dashboard.

### 3. The Active Project Scope Banner
Rendered on `admin/roles.blade.php` when viewed with `?projectId=...`:
- Emphasizes that custom role schemas are available platform-wide, but user assignments apply specifically to the current project.
- Provides direct navigation to the project's **Team Roster** (`admin/project/{id}#tab-team`) or an option to switch to **View All Projects** (`admin/roles`).

### 4. Contextual Role Assignment Modal
When clicking `👤+ Assign User`:
- The JavaScript function `openAssignUserToRoleModal(roleId, roleName, defaultProjectId, defaultProjectTitle)` dynamically updates:
  1. The modal title: `👤+ Assign User to Developer in My Demo App`
  2. The target project banner: Displays `Target Project Scope: My Demo App (demo-app)`
  3. The dropdown `<select name="project_id">`: Automatically pre-selects `demo-app`
  4. The submit button: `Confirm Assignment to My Demo App`
  5. The hidden input: `<input type="hidden" name="return_project_id" value="demo-app">`
- Upon form submission, `AdminPMController::assignRoleMember()` assigns the user and redirects back to `/admin/roles?projectId=demo-app`, preventing context loss.

### 5. Unified Project Chips on Sub-Pages
All project-specific administration screens maintain visual consistency using `.adm-title-project-chip`:
- **CI/CD Automations**: `⚡ CI/CD Automation Rules [📁 My Demo App]`
- **Git & Outbox Webhooks**: `🪝 Git & Outbox Webhooks [📁 My Demo App]`
- **Media Library**: `🖼️ Media Library [📁 My Demo App]`
- **Project Settings**: `Manage: My Demo App [demo-app]`

---

## 4. Step-by-Step Tutorial: Assigning a Role to a Project

Follow this step-by-step walkthrough to see how project context works in practice:

### Step 1: Access Project Settings
1. Navigate to your SPPDocs admin console: `http://localhost/school1/sppdocs/admin`.
2. Click on **Manage** for your project (e.g., `My Demo App`).
3. You are now at `/admin/project/demo-app`. Notice that the top header shows `📁 Project: My Demo App (demo-app)`.

### Step 2: Open Roles & Permissions from Project Navigation
1. In the sidebar under **Project: My Demo App**, click on **🛡️ Team Members & RBAC** (or from Tab 5, click **🛡️ Manage Custom Roles & Rights →**).
2. The browser navigates to `/admin/roles?projectId=demo-app`.
3. Notice:
   - The top header retains `📁 Project: My Demo App (demo-app)`.
   - The breadcrumb strip displays `Platform / 📁 My Demo App (demo-app) / 🛡️ Team Members & RBAC`.
   - An alert banner states: `📁 ACTIVE PROJECT SCOPE: My Demo App (demo-app)`.
   - In the table, the Developer role shows: `📁 2 in project  👥 3 total`.

### Step 3: Assign a User
1. Click **👤+ Assign User** next to `Developer`.
2. The modal opens with:
   - Title: `👤+ Assign User to Developer in My Demo App`
   - Banner: `Target Project Scope: My Demo App (demo-app)`
   - The project dropdown has `My Demo App (demo-app)` pre-selected.
3. Select any registered user (e.g., `@jane`) from the dropdown.
4. Click **Confirm Assignment to My Demo App**.

### Step 4: Verification
1. The page refreshes and stays on `/admin/roles?projectId=demo-app`.
2. A success notification appears: `✅ Assigned user @jane to role 'developer' in project 'demo-app'.`
3. Click the member count button for `Developer`:
   - `@jane` appears at the top of the modal with an emerald `Active Project Scope` badge.

---

## 5. Architectural Rules & Best Practices

When adding new administration modules or extending existing views, adhere to these guidelines:

1. **Always Inspect Project Query Parameters**: If an endpoint can be reached from a project context, inspect `$_GET['projectId']` or `$_GET['project']` and validate it against `$this->config['projects']`.
2. **Pass `$project_id` and `$project` to View Controllers**: Layout rendering depends on `$project_id`. Passing it guarantees that the sidebar navigation, header badges, and breadcrumb trails stay synchronized.
3. **Preserve `return_project_id` Across POST Actions**: When forms redirect upon completion, always append `?projectId=...` if a project context was active when the action was initiated.
4. **Zero Inline Styles**: All badges, breadcrumbs, banners, and chips must use the standardized CSS classes in `src/SPPDocs/resources/css/admin.css`.

---

## 6. Graceful Context Resolution & Zero-Query Routing

### The Problem: Navigating to Bare URLs
In modern documentation and project management suites, users frequently navigate via shortcuts, browser bookmarks, or global command palettes (e.g. pressing `Ctrl+K` and selecting "View All Issues"). If the command link or bookmark points to `/issues` or `/milestones` without a `?projectId=...` query string, a naive controller that requires `$_GET['projectId']` would immediately fail with a `404 Project Not Found` error.

### The Solution: `resolveActiveProjectId()` Cascade
SPPDocs implements a robust fallback cascade via `App\SPPDocs\Traits\ProjectAwareTrait::resolveActiveProjectId()`:

```php
public function resolveActiveProjectId(?string $projectId = null): ?string
{
    // 1. Explicit parameter from route or argument
    if ($projectId && isset($this->config['projects'][$projectId])) {
        $this->setActiveSessionProject($projectId);
        return $projectId;
    }

    // 2. Query string parameter ($_GET['projectId'] or $_GET['project'])
    $queryProj = $_GET['projectId'] ?? $_GET['project'] ?? null;
    if ($queryProj && isset($this->config['projects'][$queryProj])) {
        $this->setActiveSessionProject($queryProj);
        return $queryProj;
    }

    // 3. Current active project saved in session
    $this->ensureSession();
    $sessionProj = $_SESSION['sppdocs_current_project'] ?? null;
    if ($sessionProj && isset($this->config['projects'][$sessionProj])) {
        return $sessionProj;
    }

    // 4. Default project 'demo-app' fallback
    if (isset($this->config['projects']['demo-app'])) {
        $this->setActiveSessionProject('demo-app');
        return 'demo-app';
    }

    // 5. First configured project in repository
    if (!empty($this->config['projects'])) {
        $firstKey = array_key_first($this->config['projects']);
        $this->setActiveSessionProject($firstKey);
        return $firstKey;
    }

    return null;
}
```

### In-View Project Identity Header & Switching
Every project management view (`issues/index`, `issues/board`, `issues/milestones`, `issues/calendar`, `issues/teams`) renders an intuitive project switcher dropdown alongside the view title. If multiple projects exist, users can instantly change the active project without leaving their current view or returning to the admin portal:

```blade
<div class="project-context-badge">
    <span>Project:</span>
    <select onchange="window.location.href='{{ \SPP\App::url('issues') }}?projectId=' + encodeURIComponent(this.value)">
        @foreach($all_projects as $pKey => $pCfg)
            <option value="{{ $pKey }}" {{ $pKey === $project_id ? 'selected' : '' }}>
                📁 {{ $pCfg['title'] ?? $pKey }} ({{ $pKey }})
            </option>
        @endforeach
    </select>
</div>
```

### Recursive Blade Partials & The SPP External Partials Rule
When rendering hierarchical structures like subtask trees in `issues/index.blade.php`, SPPDocs strictly enforces the **SPP External Partials Directive**:
- Instead of using raw PHP includes or un-namespaced `@include('issues/partials/issue_row')` which can fail in strict template compilers, views utilize:
  ```blade
  @spppartial('issues/partials/issue_row.blade.php', [
      'issue' => $issue,
      'project_id' => $project_id,
      'depth' => 0
  ])
  ```
- This ensures full compatibility with `ViewLocator` sandboxing and isolated BladeOne compilation.

### Avoiding the Framework Internal Route Variable Trap (`$_GET['q']`)
In standard SPP `.htaccess` rewrite rules (`RewriteRule ^(.*)$ index.php?q=$1 [L,QSA]`), Apache injects the matched request path into `$_GET['q']` (e.g., when visiting `/issues`, `$_GET['q']` is populated with `'issues'`).

Developers must be aware of this crucial architectural convention:
1. **Never Bind Search Filters to `$_GET['q']` Directly**: If a controller defines `$searchQ = $_GET['q'] ?? ''`, it will mistakenly treat the route identifier `'issues'` as an active search keyword. This causes:
   - The search input box to render pre-filled with `value="issues"`.
   - Every issue whose title or description does not explicitly contain the word "issues" to be filtered out, resulting in "No issues found" even when open issues exist.
2. **Standard Parameter Naming**: Use `name="search"` or `name="query"` in frontend search forms and controllers:
   ```php
   $searchQ = trim($_GET['search'] ?? $_GET['query'] ?? '');
   if ($searchQ === '' && isset($_GET['q']) && $_GET['q'] !== 'issues' && $_GET['q'] !== 'sppdocs/issues') {
       $searchQ = trim($_GET['q']);
   }
   ```
3. **Preserve Filter State Across Tab Navigations**: When providing status tabs (`Open`, `Closed`, `All`), always retain active search filters and parameter scopes so tab transitions feel fluid and intuitive.


