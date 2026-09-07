## `optimize:ux`

**Description**: AOT Pre-compile SPP-UX tagged templates to eliminate browser JIT parsing overhead

### Synopsis
```bash
php spp.php optimize:ux [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: DOMDocument, DOMXPath, \RecursiveIteratorIterator, \RecursiveDirectoryIterator.

