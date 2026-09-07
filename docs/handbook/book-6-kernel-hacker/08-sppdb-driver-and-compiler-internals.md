# Book 6 Chapter 8 — SPPDB Driver and Compiler Internals

Trace a database operation through the source:

```text
application query
→ SPPDB representation
→ compiler/dialect
→ SQL + parameters
→ driver/connection
→ database
```

## Lab

Trace one query for each supported compiler path you can verify in the repository.

Document which part is common and which part is backend-specific.

## Rule

Do not treat PDO, driver, compiler, and dialect as synonyms. They occupy different architectural layers.