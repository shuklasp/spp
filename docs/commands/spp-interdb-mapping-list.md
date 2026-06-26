# NAME

`spp interdb:mapping:list` - List all InterDB mappings

# SYNOPSIS

`php spp.php interdb:mapping:list`

# PURPOSE

The `interdb:mapping:list` command displays all currently configured logical-to-physical database mappings active in the InterDB module. It is a diagnostic command to quickly verify where table aliases are resolving.

# OPTIONS AVAILABLE

This command requires no additional arguments.

# UNDER THE HOOD ACTIVITY

`InterdbMappingListCommand` checks for the existence of the InterDB configuration file at `SPP_MODULES_DIR/spp/sppinterdb/etc/config.yml`. If missing, it alerts the user that InterDB is unconfigured and terminates.
If present, it parses the YAML file to extract the `mappings` array. If the array is empty, it outputs an appropriate message. Otherwise, it prints an ASCII-formatted table header. It iterates over the mappings array, padding the `alias`, `engine`, and `table` strings using `str_pad` to ensure neat, structured column alignment before echoing them to the terminal.

# EXAMPLES

**List current mappings:**
```bash
php spp.php interdb:mapping:list
```
