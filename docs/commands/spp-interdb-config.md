# NAME

`spp interdb:config` - Get or set the interdb operating mode

# SYNOPSIS

`php spp.php interdb:config [mode]`

# PURPOSE

The `interdb:config` command manages the core operating mode for the SPP InterDB module, a bridging component intended to map logical entity aliases to specific physical database engine tables. This command can either report the current configuration mode or overwrite it.

# OPTIONS AVAILABLE

- `[mode]`: (Optional) The mode string to set (e.g., `interdb`, `passthrough`, `legacy`). If omitted, the command merely prints the current mode.

# UNDER THE HOOD ACTIVITY

The `InterdbConfigCommand` targets the InterDB module configuration file situated at `SPP_MODULES_DIR/spp/sppinterdb/etc/config.yml`.
When reading the configuration, it checks if the file exists and parses it using Symfony Yaml. If no mode argument is passed, it outputs the `mode` key from the parsed array (defaulting to `interdb`).
If a mode argument is detected by iterating through `$args` and excluding CLI artifacts or option flags, it actively modifies the configuration array by updating the `mode` key. It also initializes the `mappings` array to an empty array if it doesn't already exist. To ensure safety, it recursively creates the configuration directory if it is absent, then dumps the mutated array back to the `.yml` file.

# EXAMPLES

**Check the current InterDB mode:**
```bash
php spp.php interdb:config
```

**Set the InterDB mode:**
```bash
php spp.php interdb:config strict
```
