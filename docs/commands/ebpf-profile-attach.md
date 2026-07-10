## ebpf:profile:attach

**Purpose**: Attach eBPF kernel and user-space probes to running workers for zero-overhead profiling.

### Synopsis

```bash
php spp.php ebpf:profile:attach [--type=<kprobe|uprobe>] [--symbol=<name>]
```

### Extended Usage

The `ebpf:profile:attach` command empowers systems architects to perform zero-overhead execution tracing and memory profiling. By leveraging Extended Berkeley Packet Filter (eBPF) kprobes and uprobes, it gathers granular syscall latency, memory allocation numbers, and network packet drop statistics directly from Linux kernel maps without modifying application code or impacting active request throughput.

Example:
```bash
php spp.php ebpf:profile:attach --type=uprobe --symbol=php_execute_script
```

### Options Available

- `--type=<kprobe|uprobe>`: Type of eBPF probe to attach. Defaults to `uprobe`.
- `--symbol=<name>`: Target kernel symbol or user-space function name to trace. Defaults to `php_execute_script`.

### Under the Hood Activity

1. **Strict SAPI Guarding**: Enforces secure CLI-only execution via `isCLIOnly(): bool`.
2. **Distributed Mutex Locking**: Acquires a distributed deployment lock via `TargetConnection::acquireDeploymentLock()` to prevent multiple profiler daemons from causing probe attachment collisions in kernel space.
3. **eBPF Map Communication**: Attaches the specified probes in memory and establishes communication channels with eBPF kernel maps (`syscall_latency_ns`, `memory_alloc_bytes`, `network_packet_drops`).
4. **Clean Detachment**: Aggregates real-time metrics, prints them to the console, and safely detaches all kprobes and uprobes before releasing the distributed lock.
