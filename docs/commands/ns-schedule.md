## NAME

**schedule** - Job scheduling and task running

## PURPOSE

The `schedule` namespace is a logical grouping of SPP CLI commands related to Job scheduling and task running. This namespace provides a suite of administrative and operational tools specifically designed to interact with the underlying schedule subsystems of the framework.

## UNDER THE HOOD ACTIVITY

When invoking commands within the `schedule` namespace, the CLI router isolates execution to specific modules and classes optimized for Job scheduling and task running. This modular architectural grouping prevents command collisions and ensures that each subsystem operates within its dedicated execution context.

To view the exhaustive details, options, and deep functionality of any specific command within this group, execute `php spp.php man schedule:<subcommand>`.
