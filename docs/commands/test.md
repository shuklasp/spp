# NAME
**test** - Run Parikshak Unit and Feature Tests

# SYNOPSIS
`php spp.php test [--coverage]`

# PURPOSE
A dedicated testing command to execute all Unit and Feature tests registered in the Parikshak module for the current active SPP context. 

# OPTIONS AVAILABLE
- `--coverage` : **Optional.** If provided, the test runner will collect and calculate code coverage metrics during the execution.

# UNDER THE HOOD ACTIVITY
The command resolves the current context via `\SPP\Scheduler::getContext()`. It enforces strict database isolation for tests by mutating the system configuration at runtime to use a transient, in-memory SQLite database (`:memory:`). The `\SPPMod\SPPDB\SPPDB` service provider is reset to use this environment. It then instantiates the `\SPPMod\Parikshak\SPPTestRunner` and calls `run($context, $withCoverage)`. The runner executes all tests and aggregates a summary. The CLI iterates over the returned test list, printing the status (pass/fail) with ANSI styling. If any tests fail, the script terminates with a non-zero exit code (`exit(1)`), which is useful for CI/CD pipelines.

# EXAMPLES
Run all tests:
`php spp.php test`

Run tests and generate coverage:
`php spp.php test --coverage`
