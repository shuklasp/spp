## db:migration:verify-zero-downtime

**Purpose**: Perform a dry-run analysis of database migration DDL statements to verify zero-downtime compliance and schema safety.

### Synopsis

```bash
php spp.php db:migration:verify-zero-downtime [--path=<path/to/migrations>]
```

### Extended Usage

The `db:migration:verify-zero-downtime` command protects enterprise production databases by analyzing upcoming schema changes before they are executed. It checks for potentially hazardous operations such as dropping active columns without a multi-phase release, adding non-nullable columns without default values, or creating indexes without the `CONCURRENTLY` flag.

Example:
```bash
php spp.php db:migration:verify-zero-downtime --path=/app/migrations
```

### Options Available

- `--path=<path>`: Absolute or relative path to the migrations directory to analyze. Defaults to the primary application migrations directory.

### Under the Hood Activity

1. **Distributed Mutex Locking**: Acquires a deployment lock via `\SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock()` to ensure no conflicting schema operations occur during analysis.
2. **Identifier Sanitization**: Utilizes `\SPP\Core\SchemaValidator::isValidIdentifier()` to ensure table and column names conform to strict security requirements.
3. **AST & DDL Parsing**: Evaluates DDL statements against enterprise zero-downtime deployment rules without executing them against the actual database.
4. **Clean Lock Release**: Ensures the distributed lock is safely released in a `finally` block upon completion.
