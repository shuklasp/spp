# NAME
`make:python-service` - Create a new Python service script

# SYNOPSIS
`php spp.php make:python-service <name> [--app=context]`

# PURPOSE
The `make:python-service` command scaffolds a `.py` Python microservice. This bridges SPP PHP application logic with Python's unparalleled ML, data science, and scripting ecosystem using SPP's Polyglot paradigm.

# OPTIONS AVAILABLE
- `<name>` (string, required): The core name of the service logic.
- `--app=<context>` (string, optional): Determines the target execution namespace.

# UNDER THE HOOD ACTIVITY
It defines the target directory `services/python/` mapping to the application context. By leveraging `buildFromStub()` with the `python_service` stub format, it dynamically outputs `service.{lowercase_name}.py`, injecting the `CLASS_NAME` into the boilerplate syntax natively.

# EXAMPLES
**1. Scaffold a Python text analyzer:**
```bash
php spp.php make:python-service TextAnalyzer --app=default
```
