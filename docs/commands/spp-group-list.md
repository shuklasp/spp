# NAME

`spp group:list` - List all shared resource groups

# SYNOPSIS

`php spp.php group:list`

# PURPOSE

The `group:list` command outputs a tabular summary of all shared resource groups currently registered within the SPP global settings. It provides a quick overview of group names, inheritance mappings, table prefixes, and entity counts.

# OPTIONS AVAILABLE

This command accepts no additional arguments.

# UNDER THE HOOD ACTIVITY

The `GroupListCommand` directly targets the `SPP_BASE_DIR/etc/global-settings.yml` file. It reads and decodes the YAML structure using the Symfony Yaml component. 
It accesses the `shared_groups` array from the parsed configuration. If the file is missing or the array is empty, it informs the user and terminates. 
Otherwise, it renders an ASCII table. It iterates through the associative array of groups, extracting `extends` (defaulting to 'none'), `table_prefix` (defaulting to empty string), and counting the number of items in the `entities` array. It uses PHP's `str_pad` function to maintain columnar alignment, printing the data row by row.

# EXAMPLES

**List all resource groups:**
```bash
php spp.php group:list
```
