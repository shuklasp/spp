# 13. Security Hardening (The Brutal Audit)

Welcome to the **Security Hardening** chapter. Security is the paramount feature of the SPP Framework. In order to construct an enterprise-grade portal, your core foundation must be cryptographically sound and mathematically secure against all vectors of attack.

The SPP Framework has recently undergone an exhaustive, framework-wide security audit known as the **"Brutal Audit"**. This chapter covers the major defenses implemented natively across the architecture.

---

## 1. SQL Injection (SQLi) Elimination
Legacy database interactions are highly vulnerable to SQL injection, especially in dynamic `ORDER BY` or `WHERE` clauses. 
- **QueryBuilder Sanitization:** Both `sppdb` and `sppxdb` rigidly enforce regex column sanitization (`[^a-zA-Z0-9_\.]`). Operators are actively whitelisted against a hard-coded array (`=`, `!=`, `<`, `>`, `LIKE`, `IN`, `IS NULL`, etc.). It is physically impossible to execute chained commands or subqueries through the QueryBuilder.
- **Reporting Engine Integrity:** `sppreport` completely blocks spaces and quotes in dynamic `CUSTOM` aggregate fields. This means dynamic reporting calculations are limited solely to math (`SUM(amount * tax_rate)`), completely rejecting any embedded subqueries (e.g., `(SELECT password FROM users LIMIT 1)`).

## 2. Path Traversal & LFI Mitigations
When frameworks dynamically resolve file paths based on URLs or template directives, they open themselves to Local File Inclusion (LFI).
- **View Router (`spprouter`):** Deep directory traversal tokens (`..`) are stripped, and absolute paths are validated.
- **PHP Components (`<php-include>`):** `sppview` recursively checks included files and blocks path traversal characters natively at the AST compilation layer.
- **File Disk Wrappers (`sppstorage`):** `LocalDisk` aggressively filters path traversal payloads before executing any `fopen()` or `file_get_contents()` calls.

## 3. RCE & PHP Object Injection (POP) Blocking
Deserializing arbitrary data is incredibly dangerous and historically leads to Remote Code Execution via POP chains.
- **Safe Caching:** `sppcache` executes `unserialize()` with the strict `['allowed_classes' => false]` directive. If a malicious payload attempts to inject an object, PHP safely neuters it into `__PHP_Incomplete_Class`.
- **Inheritance-Bound Queues:** `sppqueue` job deserialization rigorously enforces `is_subclass_of($job, \SPP\Job::class)`. Rogue classes are dropped from memory instantly before any magic methods (`__destruct()`) can fire.
- **Code Stub Generation:** `sppmaker` securely regex-whitelists entity names (`\w+`) to prevent evaluating arbitrary code within the generation templates.

## 4. Log Forging (Injection)
When logging user behavior, inserting unfiltered payloads like HTTP User Agents directly into a text log file can allow attackers to inject carriage returns (`\r\n`) and write falsified log lines.
- **CRLF Stripping:** `spplogger` actively sanitizes user-supplied HTTP headers and messages using `str_replace()`, condensing multiline payloads and preventing log manipulation.

## 5. WebSocket Integrity
WebSockets typically suffer from cross-site websocket hijacking (CSWSH) and unauthenticated broadcast spoofing.
- **HMAC Signatures:** The `spplive` Engine utilizes HMAC SHA-256 digital signatures (`X-SPP-Live-Signature`) on all internal broadcast API requests. 

By utilizing the standard framework primitives (`SPPDB`, `SPPLogger`, `SPPCache`, `SPPQueue`, `LiveComponent`), your application automatically inherits this impenetrable defense matrix.
