# xdb:list-tables

## NAME
`xdb:list-tables` - List all tables in an XDB database.

## SYNOPSIS
`php spp xdb:list-tables [--db=<dbname>]`

## PURPOSE
Displays all the available tables within a specified SPPXDB database context.

## OPTIONS AVAILABLE
* `--db=<dbname>`: Specifies which database to inspect. If omitted, defaults to `default`.

## UNDER THE HOOD ACTIVITY
The `xdb:list-tables` command begins by parsing arguments to extract the `--db=` parameter; if it is not provided, it falls back to targeting the `'default'` database. It then loads the `class.sppxdb.php` file from the `modules/spp/sppxdb` directory.

It instantiates the `SPP_XDB` class, passing the targeted database name into its constructor. The tool executes the SQL query `SHOW TABLES` via the `$xdb->querySQL()` method. 

Once the result set is retrieved, it checks if it is empty. If tables are found, it iterates over each row of the result. Since `SHOW TABLES` typically returns rows with a single value containing the table name, it uses the `current()` PHP function to extract and print each table name in a formatted list. Error handling catches and prints any exceptions that happen during the execution.

## EXAMPLES
* `php spp xdb:list-tables`
* `php spp xdb:list-tables --db=ecommerce`
