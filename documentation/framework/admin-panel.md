# SPP Admin Panel: Developer Workbench

The SPP Admin Panel is a high-performance, single-page application (SPA) designed for framework orchestration, application management, and system diagnostics. It provides a "Glassmorphism" inspired interface for managing every layer of the SPP ecosystem.

---

## 🏛️ 1. Dashboard & System Information
The entry point of the workbench provides a high-level overview of the framework's health and environment.

*   **Framework Status**: Displays the active SPP version and system uptime.
*   **Database Connectivity**: Real-time status of the primary database engine.
*   **System Stats**: Aggregated count of registered apps, active modules, entities, and forms.
*   **Environment Details**: Details on Operating System, PHP version, and Server Software.
*   **Polyglot Bridge**: Status of cross-language runtimes (Python, Node.js, Ruby, Go).
    *   **Action: Refresh Bridge**: Re-scans the system for installed runtimes and regenerates the shared configuration.

---

## 🌐 2. Infrastructure & Ecosystem

### Applications & Sharing
Manage the multi-tenant application registry.
*   **Application Tab**: 
    *   **Base URL**: The routing prefix for the application (e.g., `/lekhak`).
    *   **Table Prefix**: Database isolation string (e.g., `spp_`).
    *   **Source Path**: The filesystem path to the app's `src/` directory.
    *   **Shared Group**: The group identifier for resource inheritance.
*   **Shared Groups Tab**: 
    *   **Create Group**: Provision a new shared resource container.
    *   **Edit Group**: Manage group-level entity associations and isolation modes.

### InterDB Mesh
The central hub for federated data orchestration.
*   **Aggregation Mode**: 
    *   `InterDB`: Active multi-database federation with relationship stitching.
    *   `Standalone`: Lightweight GraphQL interface for a single database engine.
*   **Entity Mappings Table**:
    *   **Entity (Type)**: The unique identifier for the data object.
    *   **Engine / Alias**: Select between `Default (SQL)`, `SPPXDB (XML)`, or generic `PDO`.
    *   **Target Table**: The actual table name in the target engine.
*   **Action: Save Config**: Commits the mapping mesh to `sppinterdb/etc/config.yml`.

### XML Database (XDB)
Management interface for the Tier-1 Native XML Database.
*   **XDB Explorer**: 
    *   **Browse Collections**: Navigate the hierarchical XML structure.
    *   **Document Editor**: Direct XML/JSON editing of stored documents.
*   **Action: New Collection**: Create a new top-level data container.
*   **Action: Run Query**: Execute XQuery or GraphQL-over-XDB.

---

## 🧠 3. Application Logic

### System Modules
Configure the modular building blocks of the framework.
*   **Action: Setup**: Opens the module configuration modal.
    *   **Interactive Tab**: Edit settings via a dynamic form.
    *   **YAML Tab**: Direct raw editing of the module's `config.yml`.
*   **Action: Maintenance**: Triggers the `scan_module` API to:
    *   Check for missing database tables.
    *   Validate entity mappings.
    *   Clear module-specific caches.
*   **Action: Update**: Synchronizes the module's state with the framework registry.

### Application Entities
A dynamic CRUD interface for managing system data.
*   **Action: Create New**: Open a form to instantiate a new entity.
*   **Action: Edit/Delete**: Direct management of existing records.
*   **Context Selector**: Switch between "Core" entities and "App-Side" entities.

### Form Configurations
Manage reactive UI forms powered by `SPPForm`.
*   **Schema Preview**: Inspect the JSON/YAML structure of form definitions.
*   **Action: Refresh Registry**: Re-scan `etc/forms` for new definitions.

---

## 🔐 4. Security & Identity

### Identity & RBAC
Modernized Access Control Layer based on XDB.
*   **User Management**: 
    *   **Action: Create User**: Provision new administrative or application accounts.
    *   **Action: Password Reset**: Securely update user credentials.
*   **Role Orchestration**: 
    *   **Action: Manage Roles**: Define roles and assign specific permissions (Rights).
    *   **Action: Assign Roles**: Map roles to specific users.

### Group Management
Polymorphic group orchestration for organizational structures.
*   **Group Hierarchy**: Create and nesting groups for inheritance-based access.
*   **Member Management**: 
    *   **Action: Add Member**: Attach users, entities, or other groups to a target group.
    *   **Bucket Isolation**: Manage members across `core`, `app`, and `custom` buckets.

---

## 🛠️ 5. Technical Stack & Diagnostics

### Routing Management
Debug the application routing engine.
*   **Route Table**: 
    *   **Pattern**: The URI template for the route.
    *   **Target**: The controller method or closure responsible for the response.
    *   **Action: Test Route**: Simulate a request to verify routing behavior.

### Middleware Pipeline
Visualize the "Onion" processing layer.
*   **Global Stack**: Middleware that executes for all system requests.
*   **Action: Toggle Middleware**: Enable or disable specific layers for debugging.

### Distributed Tasks
Monitor the background job infrastructure.
*   **Task Monitor**: 
    *   **Status**: Track `queued`, `processing`, `failed`, or `completed`.
    *   **Action: Re-queue**: Restart a failed background task.
    *   **Action: Terminate**: Stop a running worker process.

### DI Service Registry
Displays registered services and bindings in the Dependency Injection (DI) Container.
*   **Abstract / Interface**: The service contract.
*   **Concrete Implementation**: The actual class instantiated.
*   **Lifecycle**: Indicates if the service is a `SINGLETON` or `FACTORY`.
*   **Action: Refresh Registry**: Re-scans the container for active bindings.

### LiveService Registry
Manage the unified reactive architecture and asynchronous services.
*   **Service Name**: The unique identifier used in `data-spp-live`.
*   **Backend Script**: The PHP file located in `src/serv/` that handles the logic.
*   **Action: Register Service**: Open a modal to map a new service name to a script.
*   **Action: Test Service**: Triggers a diagnostic request to verify the service's `LiveAction` response.

### Event Tracing
Real-time monitoring of the SPP Event Bus.
*   **Event Log**: Chronological list of fired events and their payloads.
*   **Action: Clear Trace**: Resets the in-memory event buffer.
*   **Action: Filter Trace**: Narrow down events by namespace or priority.
*   **Payload Inspector**: View the raw data objects passed through the event pipeline.

### Framework Config
Raw access to the framework's global settings.
*   **Action: Sync Settings**: Atomic save and reload of `global-settings.yml`.
*   **Action: Switch Profile**: Dynamically transition between `dev`, `test`, and `prod` environments.

---
[Back to Technical Wiki](index.md)
