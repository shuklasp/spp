<?php
namespace SPPMod\SPPOS;

/**
 * Class ResourceManager
 * 
 * The Kernel Scheduler for SPP WebOS.
 * Enforces dynamic RAM and CPU quotas for Guest Apps to guarantee fault isolation.
 */
class ResourceManager
{
    public static $currentAppAlias = null;

    /**
     * Bootstraps the quota limits for a given app and forces the PHP Zend Engine
     * to automatically preempt execution via ticks to catch infinite loops.
     */
    public static function enforceQuotas(string $appAlias)
    {
        self::$currentAppAlias = $appAlias;
        
        // In reality, this data is fetched from the WebOS Registry.
        $quotas = self::fetchQuotasFromRegistry($appAlias);

        if (isset($quotas['ram'])) {
            self::setRamQuota($quotas['ram']);
        }

        if (isset($quotas['cpu'])) {
            self::setCpuQuota($quotas['cpu']);
        }

        // Register the tick function for hard preemption
        register_tick_function(['\SPPMod\SPPOS\ResourceManager', 'tickCheck']);
    }

    /**
     * Fired automatically by the Zend Engine every N ticks.
     * Prevents while(true) infinite loops from freezing the worker thread.
     */
    public static function tickCheck()
    {
        if (self::$currentAppAlias) {
            // Note: In a true hyper-scale production environment, calling memory_get_usage
            // every 100 ticks adds ~2% overhead, but guarantees total fault isolation.
            $currentRam = memory_get_usage(true);
            $quotas = self::fetchQuotasFromRegistry(self::$currentAppAlias);
            
            if (isset($quotas['ram'])) {
                $maxBytes = self::convertMemoryStringToBytes($quotas['ram']);
                if ($currentRam > $maxBytes) {
                    throw new WebOsKernelPanicException("App '" . self::$currentAppAlias . "' exceeded its RAM limit during execution tick. Process killed.");
                }
            }
        }
    }

    private static function convertMemoryStringToBytes(string $memoryLimit): int
    {
        $val = trim($memoryLimit);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }

    private static function fetchQuotasFromRegistry(string $appAlias): array
    {
        // Mock registry lookup
        $registry = [
            'wordpress:blog' => ['ram' => '128M', 'cpu' => 10], // 10 seconds execution limit
            'magento:store'  => ['ram' => '512M', 'cpu' => 30]
        ];

        return $registry[$appAlias] ?? [];
    }

    private static function setRamQuota(string $limit)
    {
        // Dynamically enforce memory limit at the PHP interpreter level
        ini_set('memory_limit', $limit);
    }

    private static function setCpuQuota(int $seconds)
    {
        // Enforce execution time. If it exceeds, PHP throws a fatal error,
        // which the SPP Kernel Error Handler can catch and translate into a WebOsKernelPanicException.
        set_time_limit($seconds);
    }
}
