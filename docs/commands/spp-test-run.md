# NAME

`test:run` - Runs Parikshak evaluation for an entity or the whole suite

# SYNOPSIS

`php spp.php test:run [<EntityClass>] [--app=<appname>] [--coverage]`

# PURPOSE

Provides a unified interface for executing the Parikshak testing suite. It can evaluate a single specific entity or run the full suite of evaluations for an application context.

# OPTIONS AVAILABLE

- `<EntityClass>`: (Optional) The specific entity to test. If omitted, the full suite for the application is executed.
- `--app=<appname>`: Specify the application context. Defaults to `default`.
- `--coverage`: Pass this flag to instruct the testing engine to measure and report code coverage (if supported by the engine).

# UNDER THE HOOD ACTIVITY

The command relies on the base `Command` class's helper methods `getOption` and `getArgument` to parse the app name, entity name, and coverage flags. It bootstraps the environment into the requested application context using `\SPP\Scheduler::withContext()`. 

The core logic attempts to load the `parikshak` module (`\SPPMod\Parikshak\Parikshak`). If a specific entity was provided, it simply calls `$tester->testEntity($entity, $appname)`. However, if no entity is provided, it calls `$tester->runSuite($appname, $withCoverage)` which triggers a full contextual evaluation loop. For a suite run, it retrieves a structured results array and outputs a brief summary detailing the number of passed assertions against the total evaluated.

# EXAMPLES

**Run the full test suite for the default app:**
```bash
php spp.php test:run
```

**Run the full test suite with coverage for the frontend app:**
```bash
php spp.php test:run --app=frontend --coverage
```

**Test only the Invoice entity:**
```bash
php spp.php test:run "App\Entities\Invoice"
```
