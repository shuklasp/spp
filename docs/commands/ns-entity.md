## NAME

**entity** - Entity and ORM management

## PURPOSE

The `entity` namespace is a logical grouping of SPP CLI commands related to Entity and ORM management. This namespace provides a suite of administrative and operational tools specifically designed to interact with the underlying entity subsystems of the framework.

## UNDER THE HOOD ACTIVITY

When invoking commands within the `entity` namespace, the CLI router isolates execution to specific modules and classes optimized for Entity and ORM management. This modular architectural grouping prevents command collisions and ensures that each subsystem operates within its dedicated execution context.

To view the exhaustive details, options, and deep functionality of any specific command within this group, execute `php spp.php man entity:<subcommand>`.
