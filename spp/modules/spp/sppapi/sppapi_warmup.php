<?php
/**
 * SPP API Module Custom Cache Warmup Handler
 * Automatically discovered and executed by `php spp.php cache:warmup`.
 */
namespace SPPMod\SPPAPI;

echo "-> Executing custom warmup for SPPAPI module...\n";
if (class_exists('\\SPP\\Cache')) {
    // Pre-cache standard API schema definition metadata
    \SPP\Cache::set('sppapi_schema_version', 'v2.4.1', 86400);
    echo "-> SPPAPI schema version cached successfully.\n";
}
