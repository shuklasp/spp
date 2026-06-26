# NAME

`spp group:delete` - Delete a shared resource group

# SYNOPSIS

`php spp.php group:delete <group_name>`

# PURPOSE

The `group:delete` command removes a shared resource group from the SPP global settings. It implements safety checks to prevent deletion of a group that is currently assigned to any active application in the framework.

# OPTIONS AVAILABLE

- `<group_name>`: The unique identifier of the resource group you wish to delete.

# UNDER THE HOOD ACTIVITY

The `GroupDeleteCommand` parses `SPP_BASE_DIR/etc/global-settings.yml` utilizing the Symfony Yaml component. It verifies that the specified `group_name` exists inside the `shared_groups` array.
Before deleting, the command performs a dependency check by iterating over the `apps` section of the global configuration. It inspects each app's configuration to see if its `shared_group` matches the requested `group_name`. If one or more apps are actively using the group, the command compiles a list of those applications, outputs an error preventing the deletion, and aborts.
If no dependencies are found, the group is `unset()` from the `shared_groups` array. The configuration is then re-serialized with `Yaml::dump` and persisted back to `global-settings.yml`.

# EXAMPLES

**Delete an unused resource group:**
```bash
php spp.php group:delete old_legacy_group
```
