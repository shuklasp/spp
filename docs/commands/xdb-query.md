# xdb:query

## NAME
`xdb:query` - Execute a SQL or XPath query on the XML database.

## SYNOPSIS
`php spp xdb:query "<query>" [--type=<sql|xpath>]`

## PURPOSE
Executes queries directly against the SPPXDB system using the command line. This allows developers to interact with the underlying XML-based data structures without needing a specialized client, supporting both traditional SQL-like queries and raw XPath expressions.

## OPTIONS AVAILABLE
* `"<query>"`: The actual SQL or XPath string to execute. It should be enclosed in quotes to prevent shell parsing issues. This is the first non-option argument.
* `--type=<sql|xpath>`: Specifies the query engine to use. Defaults to `sql`.
  - `sql`: Uses the SQL parser to interpret the query.
  - `xpath`: Passes the query directly to the XPath engine.

## UNDER THE HOOD ACTIVITY
When `xdb:query` is invoked, it parses the command-line arguments to separate the query string from the options. It ignores the script name (`spp.php` or `spp/spp.php`) and the command name itself (`xdb:query`). It extracts the `--type` flag if present, defaulting to `sql` otherwise.

The command then dynamically attempts to locate and load the `SPP_XDB` core class from `modules/spp/sppxdb/class.sppxdb.php`. If the file is missing, it throws an exception.

Once the class is loaded, it instantiates `\SPPMod\SPPXDB\SPP_XDB`. Depending on the query type specified, it calls either `$xdb->queryX($query)` for XPath expressions or `$xdb->querySQL($query)` for SQL statements. The XPath execution assumes the user is either targeting a global scope or implicitly relying on a default connection state. Finally, the results are captured; if an array of records is returned, it prints the count and the raw output via `print_r`. If a non-array response (like a boolean for an insert/update) is returned, it outputs the result using `var_export`.

## EXAMPLES
* `php spp xdb:query "SELECT * FROM users"`
* `php spp xdb:query "//user[age>18]" --type=xpath`
