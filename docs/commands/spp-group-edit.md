# NAME

`spp group:edit` - Edit an existing shared resource group

# SYNOPSIS

`php spp.php group:edit <group_name> [--extends=<group>] [--prefix=<prefix>]`

# PURPOSE

The `group:edit` command allows modifying the attributes of an existing shared resource group in the global settings, such as changing its parent inheritance or its table prefix.

# OPTIONS AVAILABLE

- `<group_name>`: The unique identifier of the resource group to edit.
- `--extends=<group>`: Replaces the parent group inheritance.
- `--prefix=<prefix>`: Replaces the database table prefix.

# UNDER THE HOOD ACTIVITY

The `GroupEditCommand` loads the global configuration from `SPP_BASE_DIR/etc/global-settings.yml` using the Symfony Yaml parser. It checks whether the provided `group_name` actually exists in the `shared_groups` array, emitting an error if it is not found.
It iterates over the command-line arguments, searching for `--extends=` and `--prefix=`. If either is found, it updates the corresponding key inside the in-memory array representation of the specific group and flags that an update occurred.
If changes were detected, it writes the modified settings array back to disk using `Yaml::dump`. If no modifiers were provided, it gracefully skips writing and instead utilizes `print_r` to dump the current configuration array of the group to the console for inspection.

# EXAMPLES

**Change a group's prefix:**
```bash
php spp.php group:edit my_group --prefix=new_pfx_
```

**View a group's current configuration without changing it:**
```bash
php spp.php group:edit my_group
```
