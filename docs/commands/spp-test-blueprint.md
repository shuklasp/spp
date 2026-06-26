# NAME

`test:blueprint` - Generate a structural blueprint for an entity

# SYNOPSIS

`php spp.php test:blueprint <EntityClass> [--app=<appname>]`

# PURPOSE

The `test:blueprint` command generates a detailed structural blueprint representation of a given entity class using the SPP Framework's internal Parikshak testing module. This is used to understand the schema, attributes, and relationships mapping of the entity prior to running comprehensive suite tests.

# OPTIONS AVAILABLE

- `<EntityClass>`: The fully qualified class name or resolvable alias of the entity you want to generate a blueprint for. (Required)
- `--app=<appname>`: Specify the application context under which this command should execute. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

When executed, the command parses the CLI arguments to identify the target entity and application context. It invokes `\SPP\Scheduler::withContext()` to temporarily sandbox the execution within the requested application environment. Inside the context, it actively tries to load the `parikshak` module via `\SPP\Module::loadModule('parikshak')`.

If the `\SPPMod\Parikshak\Parikshak` class exists (indicating the module is installed and active), it instantiates the tester class and invokes the `generateBlueprint($entity)` method. Finally, the resulting structural blueprint array is outputted to the standard output using PHP's native `print_r()`.

# EXAMPLES

**Generate a blueprint for the User entity:**
```bash
php spp.php test:blueprint "App\Entities\User"
```

**Generate a blueprint in a specific app context:**
```bash
php spp.php test:blueprint "App\Entities\Payment" --app=billing
```
