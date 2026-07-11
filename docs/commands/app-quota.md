## `app:quota {alias} {--ram=} {--cpu=}`

**Purpose**: Configures hardware boundaries (RAM/CPU) for WebOS Guest Apps to ensure fault isolation and prevent resource starvation.

### Synopsis
```bash
php spp.php app:quota wordpress:blog --ram=128M --cpu=10s
```

### Extended Usage
The `app:quota` command is a core component of the SPP WebOS `ResourceManager`. 
When deploying untrusted or legacy third-party Guest Apps (like Magento or WordPress) alongside your Native SPP microservices, there is a risk that a memory leak or infinite loop in the Guest App could crash the entire OS.
By setting quotas, you instruct the SPP Kernel Scheduler to strictly monitor the instance's footprint. If the instance exceeds its allocation, the Kernel intercepts the execution, kills the thread gracefully, and throws a `WebOsKernelPanicException` to save the server.

### Options Available
*   **`alias`** (Required): The unique IAM identifier of the guest app (e.g., `wordpress:blog`).
*   **`--ram=`** (Optional): The memory limit string accepted by PHP (e.g., `128M`, `1G`).
*   **`--cpu=`** (Optional): The maximum execution time in seconds (e.g., `10s`, `30s`).

### Under the Hood Activity
1.  **Filesystem Writes**: This command parses the CLI arguments and writes the updated quota definitions into the centralized WebOS YAML Registry (typically `etc/integrations.yml`).
2.  **Kernel Interception**: During the next HTTP request to the guest app, the `ResourceManager` reads the registry and dynamically invokes `ini_set('memory_limit')` and `set_time_limit()` strictly bound to that specific execution thread.
