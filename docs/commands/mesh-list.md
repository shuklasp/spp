## mesh:list

**Purpose**: Lists all active Mesh passthrough routes.

### Synopsis

```bash
php spp.php mesh:list
```

### Extended Usage

The `mesh:list` command queries the active Mesh routing registry and outputs a formatted table of all currently mounted legacy applications, including their absolute filesystem targets and any injected features. It is highly recommended to run this command after modifying the mesh to verify the exact integration parameters.

### Options Available

None.

### Under the Hood Activity

1. Reads the `etc/mesh.yml` file from the filesystem.
2. Parses the configuration and prints a hierarchical tree detailing the routes, targets, and active features.
