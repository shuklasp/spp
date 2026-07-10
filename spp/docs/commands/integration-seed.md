## integration:seed

**Purpose**: Bulk seed local SPP users into a specific integration target.

### Synopsis
```bash
php spp.php integration:seed <app_name>
```

### Extended Usage
The `integration:seed` command automates the mass migration and synchronization of the historical SPP user base into a newly attached external application.

For example, if your SPP application has been running for 2 years with 5,000 users, and you decide to install Magento today, Magento will have 0 users. By running this command, SPP will query the local `spp_users` table, extract the identities, and iteratively use the `MagentoDriver` to bulk-inject all 5,000 users natively into Magento.

### Options Available
*   `app_name` (string, required): The alias of the registered target driver (e.g., `magento`, `discourse`).

### Under the Hood Activity
*   **Filesystem Writes**: None.
*   **DB Interactions**: Performs a mass `SELECT` query on the local `spp_users` table.
*   **Outbound HTTP Calls**: Will generate outbound HTTP calls if the selected driver (e.g., Discourse) communicates via REST API rather than Native Bootstrapping.
