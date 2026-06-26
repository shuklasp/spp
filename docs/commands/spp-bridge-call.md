# NAME
spp bridge:call - Internal RPC bridge to invoke PHP methods from Polyglot clients

# SYNOPSIS
`php spp.php bridge:call <class> <method> [argsJson]`

# PURPOSE
Serves as an internal Remote Procedure Call (RPC) gateway, enabling external multi-language runtimes (Polyglot clients like Python or Node.js) to seamlessly execute arbitrary PHP classes and methods within the SPP framework and retrieve the results via standard output JSON strings.

# OPTIONS AVAILABLE
- `<class>` : The fully qualified namespace path of the target PHP class. Dot-notation (`App.Core.Service`) is supported and automatically translated to PHP backslashes (`App\Core\Service`).
- `<method>` : The specific method name to invoke on the target class.
- `[argsJson]` : A JSON-encoded array containing ordered parameters to pass to the invoked method. Defaults to `[]`.

# UNDER THE HOOD ACTIVITY
1. **Sanitization:** Validates presence of both class and method. It allows incoming clients to specify namespaces using dot notation by `str_replace('.', '\\', $class)`, which mitigates command-line escaping headaches.
2. **Decoding:** Uses `json_decode()` on the `$argsJson` string to rebuild the PHP parameter array.
3. **Reflection & Dispatch:** Leverages PHP's `\ReflectionMethod` to analyze the target.
   - If the method is determined to be **static** (`isStatic()`), it bypasses instantiation and invokes it immediately via `call_user_func_array([$class, $method], $params)`.
   - If the method is **instance-bound**, it requests a singleton instance from the internal Service Container (`\SPP\App::getInstance()->get($class)`) and invokes the method on that specific instance.
4. **Output formatting:** Catches all exceptions. Wraps the output array in a standardized JSON envelope `{"success": true|false, "data": ..., "error": ...}` and echoes it to STDOUT, where the bridging Polyglot worker captures it over the IPC pipe.

# EXAMPLES
Invoke a static helper method with no arguments:
`php spp.php bridge:call SPP.Helpers.Math getPi`

Invoke a service container method with JSON payload arguments:
`php spp.php bridge:call App.Services.EmailService send "[\"admin@example.com\", \"Alert\"]"`
