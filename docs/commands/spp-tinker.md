# NAME

`tinker` - Interact with your application in a REPL shell

# SYNOPSIS

`php spp.php tinker [--force]`

# PURPOSE

Launches an interactive Read-Eval-Print Loop (REPL) directly within the SPP Framework context. It allows developers to execute raw PHP code, interact with entities, call services, and debug application states in real-time.

# OPTIONS AVAILABLE

- `--force`: By default, Tinker refuses to run if `APP_ENV` is not set to `local`. Passing this flag overrides the safety check and allows execution in non-local environments.

# UNDER THE HOOD ACTIVITY

The command implements a strict security check upon initialization by verifying the `APP_ENV` environment variable. If the environment is not `local` and the `--force` flag is not present, it gracefully exits to prevent accidental database manipulations in production.

Once started, it enters an infinite `while (true)` loop utilizing `fgets(STDIN)` to capture user input. It intelligently sanitizes the input: if the expression lacks a trailing semicolon or closing brace, it appends a semicolon. It also attempts to evaluate snippets as inline expressions (by prepending `return `) unless the snippet explicitly starts with statements like `echo`, `return`, `class`, `function`, or a variable assignment `$`.

The actual execution happens inside a `try/catch` block wrapping an `eval()` call. Output buffering (`ob_start()` / `ob_get_clean()`) captures any implicit output. The evaluated result is output via `var_dump()`. If a `ParseError` occurs due to the `return ` prepend logic, it falls back to a strict `eval()` attempt. Any execution anomalies or exceptions are cleanly caught and printed to the console rather than crashing the REPL loop.

# EXAMPLES

**Start the Tinker REPL:**
```bash
php spp.php tinker
```

**Force start Tinker on a remote staging server:**
```bash
php spp.php tinker --force
```
