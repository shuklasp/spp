<?php

namespace SPP\Core\Interfaces;

/**
 * Interface SPPSessionBridgeInterface
 * Handles the persistence and synchronization of session state.
 */
interface SPPSessionBridgeInterface
{
    /**
     * Synchronize session variables to a persistent bridge.
     */
    public function sync(string $sessionId, array $sessionVars): void;

    /**
     * Destroy the bridged session state.
     */
    public function destroy(string $sessionId): void;
}
