<?php

namespace SPP\Core\Polyglot;

interface PolyglotBridgeInterface
{
    /**
     * Get the command to execute for the specific language.
     *
     * @param string $module
     * @param string $func
     * @param array $args
     * @param array $runtime
     * @param string $sharedDir
     * @return string
     */
    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string;

    /**
     * Executes custom setup if required (e.g. compilation).
     * Should return ['success' => false, 'error' => '...'] on failure, or null on success.
     */
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array;
}
