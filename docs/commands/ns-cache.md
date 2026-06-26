## NAME

**cache** - Application and Redis cache management

## PURPOSE

The `cache` namespace is a logical grouping of SPP CLI commands related to Application and Redis cache management. This namespace provides a suite of administrative and operational tools specifically designed to interact with the underlying cache subsystems of the framework.

## UNDER THE HOOD ACTIVITY

When invoking commands within the `cache` namespace, the CLI router isolates execution to specific modules and classes optimized for Application and Redis cache management. This modular architectural grouping prevents command collisions and ensures that each subsystem operates within its dedicated execution context.

To view the exhaustive details, options, and deep functionality of any specific command within this group, execute `php spp.php man cache:<subcommand>`.
