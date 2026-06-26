# NAME
spp config:sync - Synchronize framework configurations to DB schemas

# SYNOPSIS
`php spp.php config:sync [all|workflows|fields]`

# PURPOSE
Aligns file-based configurations and schema programmatic structures with actual database physical states, provisioning missing required tables.

# OPTIONS AVAILABLE
- `workflows` : Synchronizes WorkflowManager states.
- `fields` : Asserts and provisions dynamic EAV field storage schemas.
- `all` : (Default) Synchronizes everything.

# UNDER THE HOOD ACTIVITY
- **Workflows:** Checks classloader for `\SPP\Core\WorkflowManager`. Validates all registered in-memory workflow configurations.
- **Fields:** Manages the `spp_entity_fields` polymorphic storage table. Evaluates the local DB dialect. If it's a MySQL engine, it provisions standard `VARCHAR`/`TEXT`/`DECIMAL` composite key structures. If it identifies `sqlite`, it swaps datatypes to standard `TEXT`/`REAL`/`INTEGER` syntax and iterates over statement slices properly. It ensures EAV (Entity-Attribute-Value) schemas exist to handle unstructured inputs securely.

# EXAMPLES
`php spp.php config:sync fields`
