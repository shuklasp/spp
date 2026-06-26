# NAME
`make:service` - Create a new service class

# SYNOPSIS
`php spp.php make:service <name> [--app=appname] [--lang=python|node|go|dotnet|perl|java]`

# PURPOSE
The `make:service` command provisions a business logic service class. What makes this command exceptionally powerful is its built-in integration with the SPP Polyglot Worker system. It can scaffold standard PHP services or intelligently scaffold microservices in external languages (like Python or Go) while simultaneously generating a perfectly mapped PHP Proxy class to interface with them.

# OPTIONS AVAILABLE
- `<name>` (string, required): The target identifier for the service logic.
- `--app=<appname>` (string, optional): The application namespace (e.g. `school`, `admin`).
- `--lang=<language>` (string, optional): Indicates the target programming language. Valid options include `php` (default), `python`, `node`, `go`, `dotnet`, `perl`, and `java`.

# UNDER THE HOOD ACTIVITY
First, it extracts the target application context and the intended language.
If the `--lang` flag matches an external language (e.g., `python`), it physically instantiates and executes the specific CLI sub-command (e.g., `MakePythonCommand->execute()`), constructing the native `.py` file deep in the `services/python/` folder.
Next, it calculates the absolute path to that newly created external script. It utilizes `buildFromStub()` mapping to the `polyglot_proxy` template, passing the language explicitly (`polyglotLang`) and the absolute path (`polyglotModule`). It generates the PHP Proxy in `src/{context}/services/class.{name}.php`. This PHP Proxy class dynamically translates standard PHP method calls into IPC invocations directed at the external worker.
If no language is specified or `php` is requested, it simply builds a standard PHP Service structure using the `service` stub.

# EXAMPLES
**1. Scaffold a standard PHP service:**
```bash
php spp.php make:service PaymentGateway --app=billing
```

**2. Scaffold a Python service with a PHP Proxy:**
```bash
php spp.php make:service ImageProcessor --lang=python --app=media
```
