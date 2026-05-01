# SPP Architecture: Polyglot Interoperability

Polyglot Interoperability is the framework's ability to share state, data, and lifecycle information across different programming environments (e.g., PHP, Python, Go, Node.js).

## 1. The Shared State Model
In SPP, polyglot communication is built on a **Hybrid Registry** model. While most application data remains in PHP's memory for performance, specialized data is "mirrored" to a persistent shared store.

### Key Concepts:
*   **The `__shared=>` Namespace**: Any key registered with this prefix is treated as a "Shared Global".
*   **Atomic Sync**: Changes made in PHP are immediately flushed to the filesystem.
*   **Startup Hydration**: The framework automatically loads the shared state at the beginning of every request.

---

## 2. Technical Implementation

### File-Based Source of Truth
The framework maintains a JSON-based registry at:
`[APP_BASE_DIR]/var/shared/registry.json`

### Interacting from PHP
```php
use \SPP\Registry;

// Write shared state
Registry::register('__shared=>api=>status', 'online');

// Read shared state (already pre-loaded by Scheduler)
$status = Registry::get('__shared=>api=>status');
```

---

## 3. Interacting from Other Languages

Because the state is stored in standard JSON, external services can participate in the framework's ecosystem.

### Example: Python Monitoring Service
```python
import json
import os

REGISTRY_PATH = "var/shared/registry.json"

def get_shared_key(key):
    if os.path.exists(REGISTRY_PATH):
        with open(REGISTRY_PATH, 'r') as f:
            data = json.load(f)
            return data.get(key)
    return None

# Accessing SPP state
status = get_shared_key('api')
print(f"SPP API Status: {status['status']}")
```

### Example: Node.js Background Worker
```javascript
const fs = require('fs');
const path = "var/shared/registry.json";

// Writing to SPP state from Node.js
function setSharedKey(key, value) {
    let data = {};
    if (fs.existsSync(path)) {
        data = JSON.parse(fs.readFileSync(path));
    }
    data[key] = value;
    fs.writeFileSync(path, JSON.stringify(data, null, 4));
}

setSharedKey('worker_status', 'active');
```

---

## 4. Best Practices
*   **Namespace Discipline**: Only store data that *needs* to be shared across languages in `__shared=>`. Use standard namespaces for internal PHP logic.
*   **Read-Heavy Usage**: The filesystem sync is optimized for read-heavy operations. High-frequency writes (thousands per second) should consider a Redis-based driver (see future roadmap).
*   **Immutability**: Avoid renaming core shared keys at runtime to prevent breaking external dependencies.

---
[Back to Index](index.md) | [Registry](registry.md)
