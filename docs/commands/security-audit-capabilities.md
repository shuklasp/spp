## security:audit:capabilities

**Purpose**: Audit codebase for zero-trust security compliance, CLI SAPI guarding, CSP nonces, and external partial rendering rules.

### Synopsis

```bash
php spp.php security:audit:capabilities [--path=<path/to/audit>]
```

### Extended Usage

The `security:audit:capabilities` command functions as an automated compliance and governance scanner. It ensures that all enterprise team members and automated agents continuously obey strict workspace architectural rules, preventing security vulnerabilities and maintaining zero-trust standards across the framework.

Example:
```bash
php spp.php security:audit:capabilities --path=/app/spp/modules
```

### Options Available

- `--path=<path>`: Absolute or relative path to the directory or module to inspect. Defaults to the primary application base path.

### Under the Hood Activity

1. **Strict SAPI Guarding**: Validates that the command itself is executing in a secure CLI environment via `isCLIOnly(): bool`.
2. **AST & File Traversal**: Inspects PHP files within the target path to verify that high-privilege CLI commands properly implement `isCLIOnly()`.
3. **HTML Literal Verification**: Scans controller actions and services to guarantee zero inline HTML string literals, validating that external partials (`renderPartial()`, `renderStaticPartial()`, `stream()`) are exclusively used.
4. **Middleware & CSP Check**: Confirms that public endpoints implement proper middleware routing guards and Content Security Policy (CSP) nonce generation.
