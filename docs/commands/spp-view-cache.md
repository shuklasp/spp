# NAME

`view:cache` - Pre-compiles all AST views into PHP for optimal performance

# SYNOPSIS

`php spp.php view:cache [--app=<app>]`

# PURPOSE

Enhances production performance by traversing the application's directory tree and pre-compiling all HTML and PHP views through the ViewCompiler. This prevents on-the-fly compilation in production.

# OPTIONS AVAILABLE

- `--app=<app>`: The specific application context to scan. If omitted, scans the entire `SPP_APP_DIR`.

# UNDER THE HOOD ACTIVITY

The command determines the target scanning directory by checking the `--app` option. It then utilizes PHP's `RecursiveDirectoryIterator` and `RecursiveIteratorIterator` to comprehensively traverse the directory tree. 

During iteration, it specifically targets files with `.html` or `.php` extensions. It incorporates optimization logic to skip heavy or irrelevant directories by bypassing any paths containing `var/cache` or `vendor`. For every valid file, it invokes `\SPPMod\SPPView\ViewCompiler::compile($file->getPathname())` inside a `try/catch` block, safely capturing and reporting any compilation exceptions without halting the entire process. It maintains a counter of successfully compiled files and reports the total upon completion.

Furthermore, this command exposes a `renderAdminUI()` method, injecting a dynamic HTML form into the SPP Admin Command Center, allowing execution of this CLI command directly from the browser interface.

# EXAMPLES

**Pre-compile views for all applications:**
```bash
php spp.php view:cache
```

**Pre-compile views for the frontend application only:**
```bash
php spp.php view:cache --app=frontend
```
