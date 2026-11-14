## `test:module`

**Purpose**: Run native Parikshak automated test suites for an isolated module with automated database isolation and test reporting.

### Synopsis
```bash
php spp.php test:module <modulename> [OPTIONS]
```

### Extended Usage
The `test:module` command provides first-class, isolated test execution for individual SPP modules. It discovers all test cases extending `\SPPMod\Parikshak\SPPTestCase` or `\SPPMod\Parikshak\TestCase` within the module's `tests/` directory, initializes isolated in-memory SQLite database providers, and reports test outcomes with execution timings and failure traces.

#### Examples:
```bash
# Run all tests for a specific module
php spp.php test:module sppview

# Filter tests by test class or method pattern
php spp.php test:module mymodule --filter=ComponentTest

# Run tests in compact output mode
php spp.php test:module mymodule --compact

# Show top slowest tests during execution
php spp.php test:module mymodule --profile

# Stop test execution on first failure
php spp.php test:module mymodule --bail
```

### Options Available
- `<modulename>` (Argument, required): Internal name or folder name of the target module.
- `--filter=<pattern>`: Execute only test methods or classes matching the regular expression or string pattern.
- `--compact` / `-c`: Output dots for passing tests and only print verbose failure traces.
- `--profile`: Profile test execution and display the top 5 slowest test cases.
- `--bail` / `-x`: Abort the test run immediately upon encountering the first failed assertion or error.
- `--coverage`: Generate code coverage reports if Xdebug or PCOV extensions are loaded.
- `--teamcity`: Output test events formatted for TeamCity or JetBrains CI/CD pipelines.

### Under the Hood Activity
1. **Module Resolution**: Resolves the target module directory via `\SPP\Module::loadAllModules()` and verifies `ModPath/tests` exists.
2. **Database Isolation**: Overrides `dbtype` to `sqlite` and `sqlite_path` to `:memory:` in `\SPP\DB` to isolate state from production tables.
3. **Parikshak Execution**: Dispatches test discovery and execution to `\SPPMod\Parikshak\SPPTestRunner::runDirectory()`.
4. **Lifecycle Hooks**: Triggers `setUp()`, `tearDown()`, and `RefreshDatabase` migration traits per test method.
