# di:list

## NAME
di:list - List the Dependency Injection container bindings

## SYNOPSIS
`php spp.php di:list [--app=<app_name>]`

## PURPOSE
Provides a diagnostic listing of all active bindings and singletons present inside the SPP Dependency Injection container.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Context boundary for evaluating the DI container (default: 'default').

## UNDER THE HOOD ACTIVITY
The command bootstraps into the requested application context using `\SPP\Scheduler::withContext()`. It retrieves the core application container via `\SPP\App::getApp()->getContainer()`. Due to internal visibility, it leverages PHP's `\ReflectionClass` to bypass encapsulation and extract the protected `bindings` and `instances` properties. It loops over the `bindings` array, inspecting whether each binding is mapped as a Closure or class name, classifying them as Singletons (shared) or Factories. It then merges this output with directly resolved `instances` that bypass strict initial bindings, outputting a cleanly padded, columnar table.

## EXAMPLES
```bash
php spp.php di:list
```
