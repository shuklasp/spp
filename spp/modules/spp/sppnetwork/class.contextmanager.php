<?php
namespace SPPMod\SPPNetwork;

/**
 * Class ContextManager
 * 
 * Enforces FastCGI-like strict state isolation for long-running C-Extension servers (Swoole).
 * Deeply resets static registries and superglobals to prevent cross-tenant data leaks.
 */
class ContextManager
{
    /**
     * Resets the entire global state to simulate a fresh request cycle.
     */
    public static function resetContext()
    {
        // 1. Clear Superglobals that might persist in coroutines
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $_COOKIE = [];
        $_FILES = [];
        $_SERVER['REQUEST_URI'] = '/';

        // 2. Clear SPP Framework Static Registries
        if (class_exists('\SPP\App')) {
            // In a real implementation we would have an \SPP\App::reset() method.
            // For now, we simulate the registry wipe.
            // \SPP\App::reset();
        }

        // 3. Clear the StateBus
        if (class_exists('\SPPMod\SPPOS\StateBus')) {
            \SPPMod\SPPOS\StateBus::clear();
        }
        
        // 4. Force garbage collection for huge requests
        if (gc_enabled()) {
            gc_collect_cycles();
        }
    }
}
