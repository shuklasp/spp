# NAME

`spp interdb:mapping:remove` - Remove an InterDB mapping

# SYNOPSIS

`php spp.php interdb:mapping:remove <alias>`

# PURPOSE

The `interdb:mapping:remove` command is used to delete a specific table alias mapping from the InterDB configuration file.

# OPTIONS AVAILABLE

- `<alias>`: The logical alias name of the mapping you wish to delete.

# UNDER THE HOOD ACTIVITY

`InterdbMappingRemoveCommand` iterates through positional arguments to identify the target `<alias>` string. It enforces that an alias must be provided.
It then verifies the existence of the configuration file located at `SPP_MODULES_DIR/spp/sppinterdb/etc/config.yml`. If the file does not exist, it alerts the user and halts.
If the configuration is loaded successfully via the Symfony Yaml component, it checks if the exact `<alias>` exists as a key within the `mappings` array. If found, it executes `unset()` on that array index, dumps the remaining array back into YAML format, and overwrites the configuration file.

# EXAMPLES

**Remove the 'users' mapping:**
```bash
php spp.php interdb:mapping:remove users
```
