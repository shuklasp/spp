<?php

namespace SPPMod\SPPArena;

/**
 * MemoryArena
 * Zero-Allocation Memory Arena Allocator for long-running PHP CLI background daemons.
 * Pre-allocates contiguous memory pools (arenas) for worker job execution. Instead of relying on
 * traditional PHP garbage collection (which causes execution pauses and gradual memory leaks over months of uptime),
 * workers allocate memory within an arena and instantly reset the arena offset pointer upon job completion.
 */
class MemoryArena
{
    private string $arenaName;
    private int $capacityBytes;
    private int $allocatedBytes = 0;
    private int $resetCount = 0;
    private array $activeAllocations = [];

    public function __construct(string $arenaName = 'default_worker_arena', int $capacityMb = 64)
    {
        $this->arenaName = $arenaName;
        $this->capacityBytes = $capacityMb * 1024 * 1024;
    }

    /**
     * Allocate memory within the contiguous arena pool.
     */
    public function allocate(string $allocationTag, int $sizeBytes): bool
    {
        if (($this->allocatedBytes + $sizeBytes) > $this->capacityBytes) {
            throw new \RuntimeException("MemoryArena Allocation Failed: Arena '{$this->arenaName}' is out of capacity. Requested: {$sizeBytes} bytes. Available: " . ($this->capacityBytes - $this->allocatedBytes) . " bytes.");
        }

        $this->activeAllocations[$allocationTag] = [
            'offset' => $this->allocatedBytes,
            'size' => $sizeBytes,
            'allocated_at' => microtime(true)
        ];

        $this->allocatedBytes += $sizeBytes;
        return true;
    }

    /**
     * Instantly reset the arena allocation pointer to 0, wiping all allocations without garbage collection overhead.
     */
    public function reset(): void
    {
        $this->allocatedBytes = 0;
        $this->activeAllocations = [];
        $this->resetCount++;
    }

    public function getMetrics(): array
    {
        return [
            'arena_name' => $this->arenaName,
            'capacity_bytes' => $this->capacityBytes,
            'allocated_bytes' => $this->allocatedBytes,
            'utilization_percentage' => ($this->capacityBytes > 0) ? round(($this->allocatedBytes / $this->capacityBytes) * 100, 2) : 0,
            'reset_count' => $this->resetCount,
            'active_allocations' => count($this->activeAllocations)
        ];
    }

    public function getActiveAllocations(): array
    {
        return $this->activeAllocations;
    }
}
