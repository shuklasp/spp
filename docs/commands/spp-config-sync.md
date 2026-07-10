## config:sync

**Purpose**: Synchronize framework configurations (e.g. workflows, dynamic fields) to DB schemas or system registries, asserting and provisioning missing required tables.

### Synopsis
`php spp.php config:sync [all|workflows|fields]`

### Extended Usage
Aligns file-based configurations (YAML) and programmatic in-memory definitions with actual database physical states. When syncing workflows, it provisions the `spp_workflows` state registry table and the `spp_entity_workflow_history` audit logging table, then populates the registry with all active definitions. When syncing fields, it ensures polymorphic EAV storage tables exist.

### Options Available
- `workflows` : Synchronizes WorkflowManager states, creating `spp_workflows` and `spp_entity_workflow_history` tables and importing in-memory configurations.
- `fields` : Asserts and provisions dynamic EAV field storage schemas (`spp_entity_fields`).
- `all` : (Default) Synchronizes everything.

### Under the Hood Activity
- **CLI Guarding:** Asserts execution under the CLI SAPI via `isCLIOnly()`.
- **Workflows:** Checks classloader for `\SPP\Core\WorkflowManager`. Validates all registered in-memory workflow configurations. Connects to the database to provision `spp_workflows` (storing entity_type, bundle, definition) and `spp_entity_workflow_history` (storing transition audit logs). Inserts/updates registered workflows into `spp_workflows`.
- **Fields:** Manages the `spp_entity_fields` polymorphic storage table. Evaluates the local DB dialect. If MySQL, provisions standard `VARCHAR`/`TEXT`/`DECIMAL` composite key structures. If `sqlite`, swaps datatypes to standard `TEXT`/`REAL`/`INTEGER` syntax and iterates over statement slices properly. Ensures EAV (Entity-Attribute-Value) schemas exist to handle unstructured inputs securely.
