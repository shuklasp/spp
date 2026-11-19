<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

/**
 * Interface PolyglotBridgeInterface
 *
 * Defines the contract for all SPP polyglot language adapters.
 * Supports both structured ExecutionPlan execution and backward-compatible command strings.
 */
interface PolyglotBridgeInterface
{
    /**
     * Builds a structured, shell-free ExecutionPlan for invoking the polyglot service.
     *
     * @param string $module Module file path or package identifier
     * @param string $func Function / method name to execute
     * @param array $args Arguments passed to the function
     * @param array $runtime Discovered runtime details from LanguageRegistry
     * @param string $sharedDir Shared bridge runtime directory
     * @return ExecutionPlan
     */
    public function getExecutionPlan(string $module, string $func, array $args, array $runtime, string $sharedDir): ExecutionPlan;

    /**
     * Get the legacy shell command string to execute for the specific language (backward compatibility).
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
     * Executes custom setup if required (e.g. compilation, dependency resolution).
     * Should return ['success' => false, 'error' => '...'] on failure, or null on success.
     *
     * @param string $module
     * @param string $func
     * @param array $args
     * @param array $runtime
     * @param string $sharedDir
     * @return array|null
     */
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array;
}
