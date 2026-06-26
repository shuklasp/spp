# NAME

`spp interdb:mapping:add` - Add a new InterDB mapping

# SYNOPSIS

`php spp.php interdb:mapping:add <alias> <engine> <table>`

# PURPOSE

The `interdb:mapping:add` command registers a new table alias mapping in the InterDB configuration. This enables the SPP ORM layer to transparently reroute logical database calls targeted at an alias toward a specific physical table residing on a designated database engine.

# OPTIONS AVAILABLE

- `<alias>`: The logical alias name used within application code.
- `<engine>`: The connection name or engine identifier where the target table resides.
- `<table>`: The exact name of the physical table on the destination engine.

# UNDER THE HOOD ACTIVITY

`InterdbMappingAddCommand` parses positional arguments for the alias, engine, and table variables by ignoring CLI artifacts and option flags. It demands that all three arguments are provided, or it exits early with usage instructions.
It then targets the configuration file at `SPP_MODULES_DIR/spp/sppinterdb/etc/config.yml`. If the file exists, it is parsed via Symfony Yaml; otherwise, a default structure `['mode' => 'interdb']` is initialized.
It updates or creates an entry under the `mappings` key utilizing the provided `<alias>` as the associative array key. The entry is an array consisting of `engine` and `table`. Finally, it ensures the directory structure exists and dumps the updated YAML array back to disk using `yaml_emit` (via the Yaml component).

# EXAMPLES

**Route 'users' to the 'auth' engine's 'global_users' table:**
```bash
php spp.php interdb:mapping:add users auth global_users
```
