## `polyglot:worker`

**Purpose**: Manage hardened, authenticated polyglot persistent background workers and daemons.

### Synopsis
```bash
php spp.php polyglot:worker <start|stop|restart|status|cleanup> [module] [options]
```

### Extended Usage
```bash
# Start a persistent worker for a Python module with cryptographic authentication
php spp.php polyglot:worker start path/to/service.py --lang=python

# Check the status and health ping of a running worker
php spp.php polyglot:worker status path/to/service.py --lang=python

# Stop a worker gracefully via control channel
php spp.php polyglot:worker stop path/to/service.py --lang=python

# Restart a worker
php spp.php polyglot:worker restart path/to/service.py --lang=python

# Self-heal and purge all stale PID and port files across the framework
php spp.php polyglot:worker cleanup
```

### Options Available
- `<action>`: Worker action to execute (`start`, `stop`, `restart`, `status`, `cleanup`).
- `[module]`: Relative or absolute path to the target script or module file.
- `--lang=<language>`: Language identifier (`python`, `node`, `perl`). Default: auto-detected from file extension.
- `--context=<name>`: Application isolation context for multitenant segregation. Default: `default`.

### Under the Hood Activity
- Spawns persistent worker process listening on a loopback TCP socket or Unix Domain Socket.
- Generates a cryptographically secure 32-byte authorization token required for every incoming IPC request.
- Writes atomic PID (`.pid`), port (`.port`), and metadata (`.meta.json`) files via temporary files and rename operations.
- Validates process ownership and identity matching before issuing termination signals.
- Sends graceful shutdown (`__shutdown__`) requests over the control socket prior to forced termination.
- Performs automatic health ping checks (`__ping__`) to verify worker readiness.
