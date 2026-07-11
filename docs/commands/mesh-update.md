## mesh:update

**Purpose**: Updates features for an existing mesh route.

### Synopsis

```bash
php spp.php mesh:update <uri> [--add-feature=X] [--remove-feature=Y]
```

### Extended Usage

The `mesh:update` command allows you to seamlessly modify the "A La Carte" features injected into a legacy application mounted via the SPP Mesh Router. Instead of forcing you to unmount and remount an application just to toggle a feature, this command safely applies the delta updates to the YAML registry.

### Options Available

- `<uri>`: (Required) The URL route that is currently mounted in the Mesh (e.g. `/blog`).
- `--add-feature`: (Optional) The name of a feature to inject (e.g. `hardware_quota`). Can be used multiple times.
- `--remove-feature`: (Optional) The name of a feature to remove (e.g. `ui_mesh`). Can be used multiple times.

### Under the Hood Activity

1. Parses the `etc/mesh.yml` file into a structural array.
2. Locates the specified route definition and safely modifies the `features` array.
3. Overwrites the YAML file and automatically triggers a `KernelCompiler::compile()` to ensure the routing cache is instantly refreshed for subsequent web requests.
