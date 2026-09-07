# IAM Polymorphic Groups and Access Governance in SPP

## 1. Foundational Concepts

### 1.1 What Are IAM Groups in SPP?
In the Satya Portal Pack (SPP) framework, **Identity and Access Management (IAM) Groups** represent logical collections of actors—such as users, sub-groups, or domain entities—that share common permissions, policies, and roles.

A novice developer might ask: *Why do we need groups if we already have users and roles?*
- **Role Assignment at Scale**: Instead of granting access rights to 50 individual users, you assign them to an **Administrator**, **Academic Faculty**, or **Auditor** group. Rights assigned to the group are automatically inherited by all members.
- **Polymorphic Membership**: Unlike legacy systems where groups only hold user IDs, SPP groups support **polymorphic entities**. A member can be an `SPPUser`, a custom entity (e.g., `App\Academic\Entities\Student`), or even another `SPPGroup` (nested hierarchy).
- **Dual Storage (File-Backed YAML & Database)**: SPP supports defining groups declaratively in YAML files (version-controlled alongside your code) or dynamically in database tables (`spp_groups`), discovered seamlessly through `\SPPMod\SPPAuth\SPPGroupLoader`.

### 1.2 Disambiguation: IAM Groups vs. Shared Resource Groups
A common point of confusion in SPP arises between two distinct concepts that share the word "group":
1. **IAM Groups (`SPPGroup` & `SPPGroupLoader`)**:
   - **Scope**: User authentication, role assignment, polymorphic permissions, and security policies.
   - **Storage**: `etc/apps/{app}/groups/*.yml`, `spp/etc/groups/*.yml`, or database table `spp_groups`.
   - **UI Tab**: SPP Admin `#identity` -> **Groups**.
2. **Shared Resource Groups (`shared_groups` in `global-settings.yml`)**:
   - **Scope**: Multi-tenant database architecture where multiple apps share database tables with a common prefix (e.g., `spp_` for `core`, `sch_` for `academic`).
   - **CLI Tooling**: `php spp.php group:list`, `group:create`, `group:edit`, `group:delete`.
   - **Purpose**: Low-level database multi-tenancy routing, **not** user authorization.

---

## 2. Architecture & Lifecycle

### 2.1 The Discovery Engine (`SPPGroupLoader`)
When `SPPGroupLoader::listAllGroups($appname)` executes, it performs a zero-configuration sweep across three tiers:
1. **App Tier**: Scans `etc/apps/{appname}/groups/*.yml` for application-specific groups.
2. **Global Tier**: Scans `spp/etc/groups/*.yml` for framework-wide baseline groups (`administrator`, `anonymous`, `authenticated`).
3. **Database Tier**: Queries table `spp_groups` for dynamically created runtime groups.

The result is a structured dictionary of group buckets returned with the `source` (`app`, `global`, `database`), path, and group identity.

### 2.2 Symmetrical Persistence (`SPPGroup`)
The `SPPGroup` class extends `SPPEntity` and provides unified methods regardless of whether the group lives in a file or a database:
- `load($slugOrId)`: Loads attributes from YAML or SQL table.
- `save()`: Writes changes back to YAML or SQL table.
- `delete()`: Deletes the SQL record or unlinks the YAML file and cleans up table `spp_group_members`.
- `addMember($entity, $role)`: Adds a direct entity member.
- `removeMember($entity)`: Removes an entity member.
- `getMembers($recursive)`: Returns direct or flattened recursive members.
- `isMember($entity)`: Checks membership with cycle detection.

---

## 3. Web Workbench Integration (`sppadmin`)

In the SPP Admin workbench (`sppadmin/#identity`), the **Groups** tab interfaces with the backend via standard REST/JSON endpoints:

| Action | Parameters | Description |
| :--- | :--- | :--- |
| `list_groups` | `appname` | Discovers and buckets all groups by file source or DB. |
| `list_group_members` | `group_id`, `appname` | Retrieves all direct and inherited members. |
| `add_group_member` | `group_id`, `member_entity`, `member_id`, `role` | Adds an entity to a group. |
| `remove_group_member` | `group_id`, `member_entity`, `member_id` | Removes an entity from a group. |
| `save_group` | `name`, `description`, `source` | Creates or updates a group definition. |
| `delete_group` | `id` | Permanently deletes a group and cleans up memberships. |
| `search_entities` | `q` | Autocompletes users and entities for the Fast-Add modal. |

All group endpoints require the `admin.identity` scope in `AdminRBAC.php`.

---

## 4. Step-by-Step Tutorial for Novice Developers

### Step 1: Viewing Groups in the Admin UI
1. Navigate your browser to:
   ```
   http://localhost/school1/sppadmin/#identity
   ```
2. Click on the **👥 Groups** tab.
3. You will see groups categorized under their storage sources:
   - **APP**: Application groups (e.g., `AnotherTestGroup`, `StudentClass`).
   - **GLOBAL**: System-wide groups (e.g., `Administrator`, `Anonymous`, `Authenticated`).

### Step 2: Creating a Group Programmatically via YAML
Create a new file in `etc/apps/default/groups/teachers.yml`:
```yaml
id: teachers
name: Faculty Teachers
description: Certified instructional staff with gradebook access.
attributes:
  department: Academics
members: []
```

Now, refresh the **Groups** tab in `sppadmin/#identity`. You will immediately see `Faculty Teachers` listed under `etc/apps/default/groups/teachers.yml`!

### Step 3: Managing Members
1. On any group card, click **Manage Members**.
2. Type a username (such as `admin`) into the search bar.
3. The autocomplete populates matching users via `live_IAM_SearchEntities`.
4. Click **+ Add** to link the user to the group.
5. The member list updates in real time showing the entity type, ID, and role.
6. To revoke membership, click **Remove**.

### Step 4: Deleting a Group
1. Click **Delete** on a group card.
2. Confirm the browser prompt.
3. The backend unlinks the YAML file (or deletes the database row), clears entries from `spp_group_members`, and refreshes the view.
