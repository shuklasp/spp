# NAME

`test:monkey` - Runs chaos monkey / fuzzing scenarios for an entity

# SYNOPSIS

`php spp.php test:monkey <EntityClass> [--app=<appname>]`

# PURPOSE

Unleashes the Chaos Monkey on a specified entity to test its resilience. By utilizing the Parikshak module, this command performs aggressive fuzzing scenarios—injecting malformed data, simulating random state changes, and pushing edge cases to find hidden vulnerabilities.

# OPTIONS AVAILABLE

- `<EntityClass>`: The fully qualified class name of the entity to evaluate. (Required)
- `--app=<appname>`: Specify the application context under which the test runs. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

The command parses input to extract the entity name and the application context. It uses `\SPP\Scheduler::withContext()` to sandbox the testing environment. It dynamically loads the `parikshak` module utilizing `\SPP\Module::loadModule('parikshak')`.

Once the `\SPPMod\Parikshak\Parikshak` engine is instantiated, the `testEntity()` method is called. This triggers internal fuzzing and chaos mechanisms inside the Parikshak engine against the specified entity. After completion, it calls `getResults()` to fetch the findings. The command parses the multidimensional results array to extract the last tested entity and iterates over any populated `errors` array. If errors are found, it iterates through them and outputs the vulnerabilities; otherwise, it proudly declares that the entity survived the chaos monkey.

# EXAMPLES

**Run chaos testing on the Order entity:**
```bash
php spp.php test:monkey "App\Entities\Order"
```
