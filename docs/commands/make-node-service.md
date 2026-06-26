# NAME
`make:node-service` - Create a new Node.js service script

# SYNOPSIS
`php spp.php make:node-service <name> [--app=context]`

# PURPOSE
The `make:node-service` command scaffolds a standalone JavaScript/Node.js execution service file. This allows JavaScript to be utilized natively on the server-side as a polyglot microservice within the SPP ecosystem.

# OPTIONS AVAILABLE
- `<name>` (string, required): The specific logic identifier for the JS script.
- `--app=<context>` (string, optional): Determines the target execution namespace.

# UNDER THE HOOD ACTIVITY
The command resolves the application context and constructs a target path of `services/node/service.{lowercase_name}.js`. Using the internal `buildFromStub()` mechanism against the `node_service` stub format, it injects the `CLASS_NAME` natively into the JavaScript source file.

# EXAMPLES
**1. Scaffold a Node.js data worker:**
```bash
php spp.php make:node-service DataWorker --app=default
```
