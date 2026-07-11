## mesh:remove

**Purpose**: Unmounts a legacy application from the WebOS Mesh.

### Synopsis

```bash
php spp.php mesh:remove <uri>
```

### Extended Usage

The `mesh:remove` command allows you to safely unmount a legacy application from the SPP routing tree. Once removed, SPP will no longer intercept traffic for that route as a passthrough, and standard MVC resolution or 404 behavior will resume.

### Options Available

- `<uri>`: (Required) The URL route that is currently mounted in the Mesh (e.g. `/blog`).

### Under the Hood Activity

1. Parses the `etc/mesh.yml` file and deletes the specified route entry.
2. Writes the clean YAML back to disk.
3. Automatically triggers `KernelCompiler::compile()` to ensure the routing cache is purged instantly.
