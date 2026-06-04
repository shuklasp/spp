# SPP Architecture: Polyglot Interoperability Engine

The SPP Polyglot Engine represents a massive leap in cross-language execution capabilities. It provides a seamless bridge between PHP and Python, Perl, Node.js, C++, Java, .NET, and Go. 

## A Note for Newcomers (What does this do?)
Imagine SPP as a universal translator. You can write your AI scripts in Python, your heavy math in C++, and your web server in PHP. The **Polyglot Engine** lets PHP seamlessly talk to these other languages as if they were written in PHP itself! You just type a PHP command, and in the background, SPP wakes up your Python or C++ code, hands it your data, and brings the answer right back to you instantly. No API servers to manage, no complex HTTP configurations.

---

## 1. Execution Modes

The engine runs in two distinct modes depending on performance and state requirements.

### Ephemeral Mode (One-off execution)
For quick, stateless tasks, PHP spawns a one-off shell process calling the target language script. 
Data is passed as a serialized JSON string via standard input (STDIN), and the response is captured from standard output (STDOUT).
* **Pros**: Simple, isolated, uses no background memory.
* **Cons**: Language bootstrapping time (e.g. JVM startup or Python imports) happens on every call.

### Daemon Mode (Persistent Background Socket)
For heavy frameworks (like loading PyTorch models or Spring Boot logic), SPP spawns the script as a persistent background worker. 
* The worker dynamically binds to an available TCP port.
* The port number is written to an atomic locking file in `var/shared/bridge/daemons/[hash].port`.
* PHP automatically detects the daemon, skips the bootstrap process, and fires the payload directly over the TCP socket.
* **Pros**: Near-instantaneous response times (0.002s vs 6.0s).
* **Cons**: Consumes persistent RAM.

---

## 2. Directory & Path Strictness (Developer Perspective)

To maintain absolute framework purity, **NO** framework routing or bridging files exist outside the `spp/` directory.

* **Foreign Bindings**: The static wrappers (e.g., `polyglot.hpp` for C++, `PolyglotBridge.dll` for .NET, and `polyglot.go`) are located entirely within `spp/lib/[language]/`.
* **Dispatchers**: The PHP layer dynamically generates dispatch scripts (like `dispatch.py`) directly inside `spp/lib/polyglot/`.
* **State Files**: Only temporary runtime states (e.g., `.port` files or active `registry.json` cache) are allowed in `var/shared/`.

### Architecture Flow
1. PHP executes `\SPP\PolyglotBridge::call('services/python/ai_service.py', $data)`.
2. The Polyglot Bridge checks for `daemon_service.py.port` inside `var/shared/bridge/daemons`.
3. If missing, it uses `proc_open` (and a `.vbs` hidden window wrapper on Windows or `nohup` on Linux) to spawn the daemon via `spp/lib/polyglot/dispatch.py`.
4. The daemon loads the target script, establishes a socket, and listens.
5. PHP connects to the socket and transmits JSON.
6. The foreign language executes `handle_spp_request(payload)` and writes back the output to the socket.

---

## 3. The Shared State Model (Hybrid Registry)

While execution is bridged via JSON and Sockets, passive data sharing is still maintained via the Hybrid Registry.

### Key Concepts:
*   **The `__shared=>` Namespace**: Any key registered with this prefix is treated as a "Shared Global".
*   **Atomic Sync**: Changes made in PHP are flushed to the filesystem at `var/shared/registry.json`.

### Interacting from PHP
```php
use \SPP\Registry;

// Write shared state
Registry::register('__shared=>api=>status', 'online');
```

### Interacting from Python (Reading state passively)
```python
import json, os
REGISTRY_PATH = "var/shared/registry.json"

def get_shared_key(key):
    if os.path.exists(REGISTRY_PATH):
        with open(REGISTRY_PATH, 'r') as f:
            data = json.load(f)
            return data.get(key)
    return None

status = get_shared_key('api')
```

---

## 4. Best Practices
* **Daemonizing**: Use Daemon mode for anything involving AI models, large database connections, or heavy imports.
* **Namespace Discipline**: Only store passive data that *needs* to be shared across languages in `__shared=>`. Pass active transactional data via the `PolyglotBridge::call()` payload.
* **Cleanup**: If you kill a daemon process manually from the OS, ensure you delete its corresponding `.port` file from `var/shared/bridge/daemons/` so the framework knows to restart it on the next call.

---
[Back to Index](index.md) | [Registry](registry.md)
