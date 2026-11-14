## serve

**Purpose**: Starts the interactive SPP sovereign HTTP web server with zero Apache or Nginx dependency, serving applications, static assets, APIs, and SPPAdmin workbench with live request logging.

### Synopsis
```bash
php spp.php serve [--host=0.0.0.0] [--port=8000] [--app=<name>]
```

### Extended Usage
The `serve` command runs SPP's built-in pure-PHP HTTP server fronted by the sovereign router (`spp/router.php`). It provides full parity with Apache `.htaccess` routing rules, static asset streaming with proper MIME types and caching headers, security path guards (blocking `.env`, hidden files, `.blade.php`, `.yml`), and virtual admin aliases (`/sppadmin/`, `/spp-api/`, `/sppdev/`).

Running with `--host=0.0.0.0` automatically binds to all available network interfaces, making the application accessible on `localhost` as well as other devices on the local area network (LAN/Wi-Fi).

#### Examples:
```bash
# Start server on default 0.0.0.0:8000
php spp.php serve

# Start on standard HTTP port 80 (requires administrative / sudo privileges)
php spp.php serve --port=80

# Bind strictly to localhost
php spp.php serve --host=127.0.0.1 --port=8080

# Boot with a specific application context
php spp.php serve --app=school1
```

### Options Available
- `--host=<ip>`: Interface IP address to bind to (default: `0.0.0.0` for local and network access).
- `--port=<port>`: Port number to listen on (default: `8000`).
- `--app=<name>`: Specific application context to set as default during boot.
- `--help`, `-h`: Displays command synopsis and available parameters.

### Under the Hood Activity
- Filesystem & Process Activity:
  - Invokes PHP CLI SAPI built-in web server: `php -S <host>:<port> <router.php>`.
  - Routes every request through `spp/router.php`.
  - Serves static assets directly with streaming buffers and ETag validation.
  - Passes dynamic requests into `public/index.php` front controller.
  - Interactive terminal displays real-time HTTP request status codes and access logs.
