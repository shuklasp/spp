## `test`

**Purpose**: Execute native Parikshak automated unit and feature test suites or discover registered tests across the active workspace.

### Synopsis
```bash
php spp.php test [file|filter] [--list|--discover] [--coverage] [--teamcity] [--parallel[=N]] [--watch|-w] [--update-snapshots|-u] [--bail|-x] [--failed|--retry] [--profile] [--compact|-c] [--filter=NAME]
```

### Extended Usage
The `test` command runs Parikshak automated tests for the active application context under an in-memory SQLite isolated database. When invoked with `--list` or `--discover`, it introspects all test suites and test methods across `tests/core/`, `tests/`, and application directories without executing arbitrary code, providing a rapid overview of available test coverage.

When executed with `--teamcity`, it outputs real-time TeamCity service messages for seamless integration into PhpStorm, VS Code Test Explorer, and CI pipelines. When passed `--parallel`, it partitions test suites across multiple background worker processes.

When passed `--watch` (or `-w`), Parikshak activates continuous observation mode, monitoring filesystem events in `src/` and `tests/` to instantly re-execute affected test files upon file save (`Ctrl+S`). When passed `--update-snapshots` (or `-u`), Parikshak updates recorded snapshots to match current outputs.

When passed `--bail` (or `-x`), test execution aborts immediately upon the first failure to conserve developer iteration time. When passed `--failed` (or `--retry`), Parikshak retrieves previously failing suites from `var/reports/parikshak_last_failed.json` and executes only those targets. When passed `--profile`, Parikshak measures test execution durations and highlights the top slowest test cases. When passed `--compact` (or `-c`), Parikshak renders concise dot-progress indicators.

### Options Available
- `--list`, `--discover`: Discover and print all test suites, file paths, and individual test methods.
- `--coverage`: Generate code coverage metrics (requires Xdebug or PCOV extensions).
- `--teamcity`: Output real-time TeamCity service messages for IDE and CI/CD test tree visualization.
- `--parallel[=N]`: Concurrently execute test suites across $N$ background worker processes (default: 4).
- `--watch`, `-w`: Run in continuous watch mode, automatically re-running tests on file modification.
- `--update-snapshots`, `-u`: Update stored snapshot files to match current output representations.
- `--bail`, `-x`: Immediately abort test execution on the first failure.
- `--failed`, `--retry`: Re-run only the tests/suites that failed during the most recent test run.
- `--profile`: Measure test execution timings and display the top 5 slowest test cases.
- `--compact`, `-c`: Run in concise dot-progress output mode (showing detailed diffs only on failure).
- `--filter=NAME`: Execute only test suites or methods matching the specified pattern or target path.

### Under the Hood Activity
- Introspects workspace directories using `\SPPMod\Parikshak\SPPTestRunner::discover()`.
- Automatically enforces database isolation by configuring an in-memory SQLite provider (`sqlite::memory:`) via `\SPPMod\SPPDB\SPPDB`.
- Instantiates `\SPPMod\Parikshak\SPPTestRunner` and executes tests extending `\SPPMod\Parikshak\SPPTestCase` or defined via functional DSL (`test()`, `it()`, `expect()`).
- Emits real-time IDE/CI protocol events if `--teamcity` is enabled.
- Restores original database configuration upon completion.
