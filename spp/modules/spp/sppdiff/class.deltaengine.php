<?php
namespace SPPMod\SPPDiff;

/**
 * Class DeltaEngine
 * High performance dictionary delta computation and snapshot patching engine.
 */
class DeltaEngine
{
    /**
     * Compute diff delta between old and new state arrays.
     *
     * @param array $old Original dictionary state
     * @param array $new Target/Updated dictionary state
     * @return array Computed field delta differences
     */
    public static function diff(array $old, array $new): array
    {
        $delta = [];
        
        // Exclude ephemeral time indices from auditing to focus on content deltas
        $exclusions = ['changed', 'fields_data', 'created_at', 'updated_at'];
        
        foreach ($new as $key => $val) {
            if (in_array($key, $exclusions, true)) {
                continue;
            }
            if (!array_key_exists($key, $old) || $old[$key] !== $val) {
                $delta[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $val
                ];
            }
        }

        foreach ($old as $key => $val) {
            if (in_array($key, $exclusions, true)) {
                continue;
            }
            if (!array_key_exists($key, $new)) {
                $delta[$key] = [
                    'old' => $val,
                    'new' => null
                ];
            }
        }

        return $delta;
    }

    /**
     * Patch a dictionary state backwards by applying diff delta in reverse.
     *
     * @param array $current Present state
     * @param array $delta Computed differences delta
     * @return array Reconstructed past state
     */
    public static function patch(array $current, array $delta): array
    {
        $patched = $current;
        foreach ($delta as $key => $change) {
            if (array_key_exists('old', $change)) {
                $patched[$key] = $change['old'];
            }
        }
        return $patched;
    }
}
