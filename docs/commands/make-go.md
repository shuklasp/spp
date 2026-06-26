# NAME
`make:go-service` - Create a new Go service script

# SYNOPSIS
`php spp.php make:go-service <name> [--app=context]`

# PURPOSE
The `make:go-service` command scaffolds a standalone Go (Golang) execution service designed to interact with the broader SPP ecosystem through Polyglot paradigms.

# OPTIONS AVAILABLE
- `<name>` (string, required): The identifier for the Go service.
- `--app=<context>` (string, optional): The application context namespace.

# UNDER THE HOOD ACTIVITY
When executed, the system resolves the targeted app context and standardizes the class representation. The generation engine establishes a destination path at `services/go/service.{lowercase_name}.go` within the execution context. 
It utilizes the internal `buildFromStub()` system, injecting the `$className` into the `go_service` execution stub format. The resultant Go file is created directly onto the disk, ready to be executed externally or invoked by SPP process managers.

# EXAMPLES
**1. Generate a Go processing script:**
```bash
php spp.php make:go-service DataMiner --app=analytics
```
