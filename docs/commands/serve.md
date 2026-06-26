# NAME

`serve`

# SYNOPSIS

`php spp.php serve [--port=<port>]`

# PURPOSE

Bootstraps the Universal Execution pillar by spinning up local development servers for Native, Blade, or Admin apps.

# OPTIONS AVAILABLE

- `--port=<port>` : Specify the port on which the development server should listen. Defaults to `8000`.

# UNDER THE HOOD ACTIVITY

Upon execution, the command parses the CLI arguments for the `--port=` parameter, extracting the integer port value (defaulting to 8000). It retrieves the current application context via `\SPP\Scheduler::getContext()`. It then outputs a colored console header detailing the active context, the local URL, and the administrative URL.

The process then sequentially spawns two distinct PHP server instances. First, it calculates a Hot Module Replacement (HMR) port by adding 1 to the target port. It constructs a background execution command (`start /b php -S localhost:{hmrPort} hmr.php`) and fires it asynchronously via the `exec()` function. 

Immediately following, the command constructs the foreground primary application server command utilizing PHP's built-in web server (`php -S localhost:{port} -t {SPP_APP_DIR}`). It sanitizes the document root path with `escapeshellarg()` and utilizes `passthru()` to hijack the console's standard output, effectively keeping the main process alive and bound to the server stream until the user terminates it with Ctrl+C.

# EXAMPLES

Start the server on default port 8000:
```bash
php spp.php serve
```

Start the server on port 8080:
```bash
php spp.php serve --port=8080
```
