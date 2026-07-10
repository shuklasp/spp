<?php

namespace SPPMod\SPPStorage;

/**
 * CrdtSyncEngine
 * Multi-Region Conflict-Free Replicated Data Type (CRDT) Synchronization Engine.
 * Resolves active-active database cluster write conflicts automatically using vector clocks
 * and Last-Write-Wins (LWW) element registers.
 */
class CrdtSyncEngine
{
    private array $localState = [];
    private array $vectorClock = [];
    private string $regionId;

    public function __construct(string $regionId = 'us-east-1')
    {
        $this->regionId = $regionId;
        $this->vectorClock[$this->regionId] = 0;
    }

    /**
     * Write or update a Last-Write-Wins (LWW) element register.
     */
    public function writeElement(string $key, mixed $value, ?float $timestamp = null): void
    {
        $ts = $timestamp ?? microtime(true);
        $this->vectorClock[$this->regionId]++;

        $this->localState[$key] = [
            'value' => $value,
            'timestamp' => $ts,
            'region' => $this->regionId,
            'vclock' => $this->vectorClock[$this->regionId]
        ];
    }

    /**
     * Read an element from the local CRDT register.
     */
    public function readElement(string $key): mixed
    {
        return $this->localState[$key]['value'] ?? null;
    }

    /**
     * Merge incoming state from a remote active-active geographic cluster.
     */
    public function mergeRemoteState(array $remoteState, array $remoteVectorClock): array
    {
        // Merge vector clocks (taking the element-wise maximum)
        foreach ($remoteVectorClock as $region => $clock) {
            if (!isset($this->vectorClock[$region]) || $clock > $this->vectorClock[$region]) {
                $this->vectorClock[$region] = $clock;
            }
        }

        // Merge LWW element registers
        $conflictsResolved = 0;
        foreach ($remoteState as $key => $remoteMeta) {
            if (!isset($this->localState[$key])) {
                $this->localState[$key] = $remoteMeta;
                $conflictsResolved++;
            } else {
                $localMeta = $this->localState[$key];
                // Resolve conflict using LWW timestamp comparison
                if ($remoteMeta['timestamp'] > $localMeta['timestamp']) {
                    $this->localState[$key] = $remoteMeta;
                    $conflictsResolved++;
                } elseif ($remoteMeta['timestamp'] === $localMeta['timestamp']) {
                    // Tie-breaker: Lexicographical comparison of region IDs
                    if (strcmp($remoteMeta['region'], $localMeta['region']) > 0) {
                        $this->localState[$key] = $remoteMeta;
                        $conflictsResolved++;
                    }
                }
            }
        }

        return [
            'status' => 'MERGED',
            'conflicts_resolved' => $conflictsResolved,
            'current_vclock' => $this->vectorClock
        ];
    }

    public function getState(): array
    {
        return $this->localState;
    }

    public function getVectorClock(): array
    {
        return $this->vectorClock;
    }
}
