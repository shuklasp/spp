# NAME
`make:java-service` - Create a new Java service script

# SYNOPSIS
`php spp.php make:java-service <name> [--app=context]`

# PURPOSE
The `make:java-service` command scaffolds an external microservice written in Java. It constructs a boilerplate `.java` source file mapped within the Polyglot services architecture of the SPP framework, enabling seamless interoperability between PHP request lifecycles and highly performant JVM-based executions.

# OPTIONS AVAILABLE
- `<name>` (string, required): The core identifier for the Java class. "Service" will be prepended automatically (e.g. `Auth` becomes `ServiceAuth`).
- `--app=<context>` (string, optional): The application context namespace.

# UNDER THE HOOD ACTIVITY
It sanitizes the provided name via `ucfirst()`. It maps the target directory to `services/java/` relative to the provided application context.
It provisions the necessary directories with `mkdir(..., 0777, true)` if they do not exist.
The command relies on the core `buildFromStub()` mechanism, feeding the `java_service` stub format the generated `CLASS_NAME` (which intrinsically includes the `Service` prefix). The generated `.java` file is laid out cleanly on the filesystem, structurally prepared for compilation and execution via the SPP Polyglot Worker system.

# EXAMPLES
**1. Scaffold a Java data processor:**
```bash
php spp.php make:java-service DataCruncher --app=analytics
```
