## xdb:query

**Purpose**: Execute a SQL, XPath, native XQuery 3.1, or XQuery Update Facility (XQUF) mutation against SPP XDB storage.

### Synopsis
```bash
php spp.php xdb:query "<query_string>" [--type=sql|xpath|xquery|xquf] [--json]
```

### Extended Usage
The `xdb:query` command provides a unified command-line query portal for inspecting, querying, and updating both tabular XML files and native hierarchical document collections.

#### Executing SQL Queries
```bash
php spp.php xdb:query "SELECT * FROM school.students WHERE score > 85"
```

#### Executing XPath 1.0 Lookups
```bash
php spp.php xdb:query "//row[@id='1']" --type=xpath
```

#### Executing Native W3C XQuery 3.1 (FLWOR)
```bash
php spp.php xdb:query "for \$s in collection('school')/students/student where \$s/score >= 90 return \$s" --type=xquery
```

#### Executing In-Place XQUF Mutations
```bash
php spp.php xdb:query "replace value of node //student[@id='1']/score with 99" --type=xquf
```

### Options Available
- `<query_string>`: The query expression to execute (SQL, XPath, XQuery, or XQUF). Required.
- `--type=<sql|xpath|xquery|xquf>`: Specifies the query dialect to evaluate. Defaults to `sql`.
- `--json`: Format the result set or execution status as structured JSON.

### Under the Hood Activity
- Connects to the active storage directory (`spp/modules/optional/sppxdb/data/`).
- If `--type=xquery` or `--type=xquf`, instantiates `XdbTreeEngine`, `XQueryEngine`, or `XQUFEngine` for node-level Pre/Post/Level evaluation.
- For mutations (`sql` write or `xquf`), logs change vectors to the Write-Ahead Log (`_wal.jsonl`) and persists updated tree structures.
