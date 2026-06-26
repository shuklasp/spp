# NAME

`view:service:test` - Test an AJAX service endpoint from the CLI

# SYNOPSIS

`php spp.php view:service:test --name=<service> [--app=default] [--payload='{"key":"value"}']`

# PURPOSE

Provides a robust mechanism to perform simulated end-to-end testing of AJAX endpoints directly from the command line without requiring a browser or external API client (like Postman).

# OPTIONS AVAILABLE

- `--name=<service>`: The registered name of the AJAX service to test. (Required)
- `--app=<appname>`: The application context environment. Defaults to `default`.
- `--payload=<json>`: A JSON-encoded string representing the request body data (e.g., POST variables).

# UNDER THE HOOD ACTIVITY

The script parses arguments, specifically attempting to `json_decode()` the `--payload` string. If invalid JSON is passed, it issues a warning and defaults to an empty array.

To accurately simulate an HTTP request, the command populates the superglobals `$_POST` and `$_REQUEST` with the decoded payload array, and forcefully sets `$_SERVER['REQUEST_METHOD']` to `POST`. Because SPP AJAX services often terminate execution using `exit()` after flushing their response, this command wraps the core execution (`\SPPMod\SppApi\SPPAjax::resolveAndExecute`) in an output buffer (`ob_start()`). 

It suppresses headers-already-sent warnings via the `@` operator since CLI environments handle headers differently than standard SAPI. Once execution completes (or is halted by the service), the output buffer is captured (`ob_get_clean()`). The command then attempts to locate a JSON object boundary within the raw output. If it identifies valid JSON, it actively decodes and re-encodes it using `JSON_PRETTY_PRINT` to render beautifully formatted API responses directly inside the terminal. Any internal exceptions caught during execution are also converted to clean, formatted JSON error objects.

# EXAMPLES

**Test a simple endpoint with no payload:**
```bash
php spp.php view:service:test --name=system/status
```

**Test a login endpoint with credentials:**
```bash
php spp.php view:service:test --name=auth/login --payload='{"username":"admin", "password":"password123"}'
```
