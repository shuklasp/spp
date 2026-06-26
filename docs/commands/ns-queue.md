## NAME

**queue** - Background job queues and workers

## PURPOSE

The `queue` namespace is a logical grouping of SPP CLI commands related to Background job queues and workers. This namespace provides a suite of administrative and operational tools specifically designed to interact with the underlying queue subsystems of the framework.

## UNDER THE HOOD ACTIVITY

When invoking commands within the `queue` namespace, the CLI router isolates execution to specific modules and classes optimized for Background job queues and workers. This modular architectural grouping prevents command collisions and ensures that each subsystem operates within its dedicated execution context.

To view the exhaustive details, options, and deep functionality of any specific command within this group, execute `php spp.php man queue:<subcommand>`.
