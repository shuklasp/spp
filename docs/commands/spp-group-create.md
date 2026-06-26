# NAME

`spp group:create` - Create a new shared resource group

# SYNOPSIS

`php spp.php group:create <group_name> [--extends=<group>] [--prefix=<prefix>]`

# PURPOSE

The `group:create` command creates a new shared resource group definition in the global settings. Shared resource groups allow multiple applications or modules to share database entities, tables, and configurations, usually tied together by an optional extension hierarchy and a common database table prefix.

# OPTIONS AVAILABLE

- `<group_name>`: The unique identifier for the new resource group.
- `--extends=<group>`: Specifies a parent group to inherit entity configurations from (defaults to `core`).
- `--prefix=<prefix>`: Specifies the table prefix applied to all database tables managed within this group.

# UNDER THE HOOD ACTIVITY

When executed, `GroupCreateCommand` validates the group name and attempts to load `SPP_BASE_DIR/etc/global-settings.yml` utilizing the Symfony Yaml component. It ensures that the `shared_groups` key exists within the settings array. If the specified `group_name` already exists, execution halts with an error.
It loops through the provided arguments to extract the `--extends` and `--prefix` options. A new array structure for the group is constructed containing the `extends` property, `table_prefix` property, and an empty `entities` array.
The modified settings array is re-serialized using `Yaml::dump($settings, 10, 2)` and written back to the `global-settings.yml` file via `file_put_contents`.

# EXAMPLES

**Create a basic resource group:**
```bash
php spp.php group:create saas_tenants
```

**Create a resource group with a prefix and extension:**
```bash
php spp.php group:create custom_shop --extends=shop_core --prefix=cshp_
```
