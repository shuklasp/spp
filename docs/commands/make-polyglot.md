# NAME
`make:polyglot` - Scaffold a new polyglot service

# SYNOPSIS
`php spp.php make:polyglot <language> <service_name> [--app=context]`

# PURPOSE
The `make:polyglot` command acts as an intelligent router and factory wrapper. Instead of calling language-specific commands manually (like `make:python-service`), developers can use this unified command to scaffold cross-language microservices dynamically.

# OPTIONS AVAILABLE
- `<language>` (string, required): The target programming language. Supported arguments: `python`, `node`, `go`, `java`, `cpp`, `dotnet`, `cs`, `perl`.
- `<service_name>` (string, required): The target name of the service to scaffold.
- `--app=<context>` (string, optional): Determines the target execution namespace.

# UNDER THE HOOD ACTIVITY
When executed, this wrapper maps the provided string `<language>` directly to the fully-qualified class names of specific SPP CLI command objects (e.g., `'python' => MakePythonCommand::class`, `'cs' => MakeDotNetCommand::class`).
If the language is valid, it dynamically shifts the arguments array. It synthetically rewrites `$args[1]` to mirror the underlying sub-command execution target (e.g., `make:python-service`), preserving flags like `--app`.
It then dynamically instantiates the mapped command class and invokes its `execute($newArgs)` method directly, effectively acting as an transparent proxy to the native scaffolding implementations.

# EXAMPLES
**1. Scaffold a Python ML model script:**
```bash
php spp.php make:polyglot python MLModel --app=ai_core
```

**2. Scaffold a C# worker process:**
```bash
php spp.php make:polyglot cs ThreadWorker --app=jobs
```
