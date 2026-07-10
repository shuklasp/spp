# SPP Tutorial 10: Distributed eBPF Kernel Tracing & Profiling

Welcome to **Tutorial 10** of the SPP Framework Novice-First Guide series! Whether you are a seasoned systems engineer or a total beginner who has never even heard of the SPP framework before, this guide will provide you with a complete, in-depth ("in and out") understanding of our **Distributed eBPF Kernel Tracing & Profiling** engine (`sppebpf`).

---

## 1. Foundational Concepts

### What is eBPF?
eBPF (Extended Berkeley Packet Filter) is a revolutionary technology within the Linux kernel. It allows developers to run sandboxed programs directly inside the operating system kernel without changing kernel source code or loading unstable kernel modules.

### Why does it exist in SPP?
In high-throughput enterprise PHP applications, traditional profiling tools (like Xdebug or Blackfire) add noticeable performance overhead, making them unsuitable for production environments. The `sppebpf` module solves this by attaching kernel-level probes (`kprobes`) and user-space probes (`uprobes`) directly to running worker processes. This achieves **zero-overhead profiling**, allowing architects to monitor memory allocations, syscall latency, and network packet drops in real time on active production servers.

---

## 2. Lifecycle & Architecture

The `sppebpf` engine integrates securely with SPP's CLI daemon lifecycle:

```
+-------------------------------------------------------------------------+
|                         SPP CLI CommandManager                          |
|                 (php spp.php ebpf:profile:attach)                       |
+-----------------------------------+-------------------------------------+
                                    |
                                    v (Enforces isCLIOnly(): bool)
+-----------------------------------+-------------------------------------+
|                     SPPDeploy Distributed Mutex Lock                    |
|             (TargetConnection::acquireDeploymentLock())                 |
+-----------------------------------+-------------------------------------+
                                    |
                                    v
+-----------------------------------+-------------------------------------+
|                      EbpfProfiler Engine (sppebpf)                      |
|         +-------------------------+-------------------------+           |
|         | uprobe: php_execute     | kprobe: sys_enter_epoll |           |
|         +-------------------------+-------------------------+           |
+-----------------------------------+-------------------------------------+
                                    |
                                    v
+-----------------------------------+-------------------------------------+
|                     Linux Kernel BPF Maps & Ring Buffers                |
|    (syscall_latency_ns | memory_alloc_bytes | network_packet_drops)     |
+-------------------------------------------------------------------------+
```

1. **SAPI Guarding**: The `AttachEbpfProfileCommand` verifies it is running in a secure CLI context (`isCLIOnly(): bool`).
2. **Mutex Lock Acquisition**: To prevent concurrent profilers from creating probe attachment collisions in kernel space, it acquires a distributed mutex lock via `TargetConnection::acquireDeploymentLock()`.
3. **Probe Attachment**: Instantiates `EbpfProfiler`, attaching user-space probes (`uprobes`) to PHP engine execution symbols (`php_execute_script`) and kernel probes (`kprobes`) to system calls (`sys_enter_epoll_wait`).
4. **Map Aggregation**: Reads metrics directly from kernel BPF maps (`syscall_latency_ns`, `memory_alloc_bytes`) in real time.
5. **Clean Detachment**: Once profiling completes, safely detaches all probes and releases the distributed deployment lock.

---

## 3. Step-by-Step Tutorial

### Step 1: Verify Module Registration
Ensure `sppebpf` is initialized in `modinit.php`. The framework handles this automatically when the CLI boots:

```php
// spp/modules/spp/sppebpf/modinit.php
namespace SPPMod\SPPEbpf;

if (!class_exists('\SPPMod\SPPEbpf\EbpfProfiler')) {
    require_once __DIR__ . '/EbpfProfiler.php';
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPEbpf\Commands\AttachEbpfProfileCommand')) {
        require_once __DIR__ . '/Commands/AttachEbpfProfileCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPEbpf\Commands\AttachEbpfProfileCommand());
}
```

### Step 2: Running the eBPF Profiler Daemon
To attach eBPF probes and inspect active execution metrics, execute the following CLI command in your terminal:

```bash
php spp.php ebpf:profile:attach --type=uprobe --symbol=php_execute_script
```

### Step 3: Understanding the Console Output
When executed, the daemon acquires the distributed lock, attaches the probes, and displays real-time kernel metrics:

```text
INFO: Starting SPP Distributed eBPF Kernel Tracing & Profiling Daemon...

Acquiring distributed deployment lock for eBPF probe attachment...
Distributed lock acquired successfully. Attaching uprobe to symbol php_execute_script...
--------------------------------------------------------------------------------
Probe ID / Symbol              | Probe Type      | Attachment Status
--------------------------------------------------------------------------------
php_execute_script             | uprobe          | ATTACHED       
sys_enter_execve               | kprobe          | ATTACHED       
sys_enter_epoll_wait           | kprobe          | ATTACHED       
--------------------------------------------------------------------------------

Reading real-time zero-overhead eBPF kernel map metrics:
--------------------------------------------------------------------------------
Syscall Latency (epoll/execve) : p50 = 1432ns, p99 = 9850ns (Samples: 87402)
Worker Memory Allocated        : 212.45 MB across 4 active arenas
Kernel Network Packet Drops    : 0 drops on interface eth0
--------------------------------------------------------------------------------
SUCCESS: eBPF profiling cycle complete. Zero application overhead verified.
Releasing distributed deployment lock...
Distributed lock released successfully.
```

---

## 4. Impact of Deletions & Modifications

### Legacy Behavior
Historically, developers relied on intrusive PHP extensions like Xdebug or Blackfire to trace execution bottlenecks. These tools hooked directly into the Zend Engine opcode dispatch loop, degrading production request throughput by up to 40% and distorting latency measurements.

### Rationale for Change
Modern cloud-native enterprise architectures require zero-overhead observability. By shifting profiling mechanics down into the Linux kernel via eBPF kprobes, SPP achieves absolute precision without impacting active web request handling or long-running CQRS background workers.

### Migration Path
To transition away from legacy profiling extensions:
1. Disable `xdebug.so` and `blackfire.so` in your production `php.ini` configuration.
2. Rely exclusively on `php spp.php ebpf:profile:attach` during active load testing or production bottleneck investigation.
