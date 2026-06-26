## NAME

**man** - Manual page generation

## PURPOSE

The `man` namespace is a logical grouping of SPP CLI commands related to Manual page generation. This namespace provides a suite of administrative and operational tools specifically designed to interact with the underlying man subsystems of the framework.

## UNDER THE HOOD ACTIVITY

When invoking commands within the `man` namespace, the CLI router isolates execution to specific modules and classes optimized for Manual page generation. This modular architectural grouping prevents command collisions and ensures that each subsystem operates within its dedicated execution context.

To view the exhaustive details, options, and deep functionality of any specific command within this group, execute `php spp.php man man:<subcommand>`.
