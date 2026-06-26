## NAME

**live** - LiveSync and WebSockets

## PURPOSE

The `live` namespace is a logical grouping of SPP CLI commands related to LiveSync and WebSockets. This namespace provides a suite of administrative and operational tools specifically designed to interact with the underlying live subsystems of the framework.

## UNDER THE HOOD ACTIVITY

When invoking commands within the `live` namespace, the CLI router isolates execution to specific modules and classes optimized for LiveSync and WebSockets. This modular architectural grouping prevents command collisions and ensures that each subsystem operates within its dedicated execution context.

To view the exhaustive details, options, and deep functionality of any specific command within this group, execute `php spp.php man live:<subcommand>`.
