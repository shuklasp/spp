## NAME

**di** - Dependency Injection container tools

## PURPOSE

The `di` namespace is a logical grouping of SPP CLI commands related to Dependency Injection container tools. This namespace provides a suite of administrative and operational tools specifically designed to interact with the underlying di subsystems of the framework.

## UNDER THE HOOD ACTIVITY

When invoking commands within the `di` namespace, the CLI router isolates execution to specific modules and classes optimized for Dependency Injection container tools. This modular architectural grouping prevents command collisions and ensures that each subsystem operates within its dedicated execution context.

To view the exhaustive details, options, and deep functionality of any specific command within this group, execute `php spp.php man di:<subcommand>`.
