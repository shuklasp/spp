# db:sync

## NAME
db:sync - Synchronize data between two database adapters

## SYNOPSIS
`php spp.php db:sync --from=[engine:table] --to=[engine:table]`

## PURPOSE
A CLI utility to incrementally extract, transform, and load (ETL) data between different database engines (e.g., from MySQL to XDB).

## OPTIONS AVAILABLE
- `--from=[engine:table]`: **Required**. The source database engine and table name separated by a colon.
- `--to=[engine:table]`: **Required**. The target database engine and table name separated by a colon.

## UNDER THE HOOD ACTIVITY
The script parses the `--from` and `--to` arguments to resolve the respective engine names and table designations. It initializes two separate `\SPPMod\SPPDB\SPPDB` adapter objects: a source initialized natively and a target provisioned via a custom DSN (`[engine]:dbname=default`). It extracts all records from the source table using `SELECT *`. Then, it dynamically pulls the column definitions via `getSchema()` to provision the target table using `createTableIncremental()`. Finally, it iterates over the result set, calling `insertValues()` row-by-row on the target connection.

## EXAMPLES
```bash
php spp.php db:sync --from=mysql:users --to=xdb:users_backup
```
