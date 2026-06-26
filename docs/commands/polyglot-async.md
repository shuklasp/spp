# NAME
`polyglot:async` - Internal command to execute polyglot calls asynchronously

# SYNOPSIS
`php spp.php polyglot:async [payloadB64]`

# PURPOSE
The `polyglot:async` command is an internal tool designed to trigger cross-language (polyglot) function calls asynchronously. It acts as a fire-and-forget bridge, launching a target module and executing a function in another language environment without waiting for the response.

# OPTIONS AVAILABLE
* `[payloadB64]`
  A base64 encoded JSON string representing the payload. The decoded JSON payload must contain the following keys:
  - `lang` (string): The target language of the module (e.g., 'python', 'node', 'go').
  - `module` (string): The path or identifier of the script/module to be executed.
  - `func` (string): The name of the function to execute within the target module.
  - `args` (array, optional): An array of arguments to be passed to the target function.
  - `daemon` (boolean, optional): Flag indicating whether the module runs as a daemon/worker. Defaults to false.

# UNDER THE HOOD ACTIVITY
When the `polyglot:async` command is invoked, it primarily relies on passing data through the `[payloadB64]` argument. First, the command decodes the base64 string to retrieve the underlying JSON payload. If the decoding fails or the payload is empty, the command silently exits. Upon successful decoding, it extracts the language target (`lang`), the target script or file (`module`), the specific function to invoke (`func`), and any parameters to be supplied (`args`), as well as a boolean flag `daemon`. 

With this data extracted, the command directly invokes the `\SPP\PolyglotBridge::call()` method. The execution happens within a `try-catch` block. Because this command is designed for asynchronous, "fire-and-forget" operations, it ignores the return value from the bridge call. In the event of an exception during execution, it catches the error and logs it using PHP's native `error_log` mechanism rather than bubbling it up to standard output, ensuring that the calling parent process is neither interrupted nor polluted with error outputs.

# EXAMPLES
Execute a Python script asynchronously:
```bash
php spp.php polyglot:async "eyJsYW5nIjoicHl0aG9uIiwibW9kdWxlIjoic2NyaXB0cy9oZWxsby5weSIsImZ1bmMiOiJzYXlfaGVsbG8iLCJhcmdzIjpbIldvcmxkIl0sImRhZW1vbiI6ZmFsc2V9"
```
*(The payload translates to `{"lang":"python","module":"scripts/hello.py","func":"say_hello","args":["World"],"daemon":false}`)*
