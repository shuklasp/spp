## NAME

**db** - Database migrations and verifications

## PURPOSE

The `db` namespace is a logical grouping of SPP CLI commands related to Database migrations and verifications. This namespace provides a suite of administrative and operational tools specifically designed to interact with the underlying db subsystems of the framework.

## UNDER THE HOOD ACTIVITY

When invoking commands within the `db` namespace, the CLI router isolates execution to specific modules and classes optimized for Database migrations and verifications. This modular architectural grouping prevents command collisions and ensures that each subsystem operates within its dedicated execution context.

To view the exhaustive details, options, and deep functionality of any specific command within this group, execute `php spp.php man db:<subcommand>`.
