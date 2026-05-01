<?php
namespace SPPMod\Lekhak\Core;

/**
 * Interface FilterInterface
 * Base interface for all Lekhak Pipeline Filters.
 */
interface FilterInterface
{
    /**
     * Pre-process the template before logic execution.
     */
    public function preProcess(string &$content, array &$context): void;

    /**
     * Post-process the final HTML output.
     */
    public function postProcess(string &$output, array &$context): void;

    /**
     * Get the priority of the filter (lower runs first).
     */
    public function getPriority(): int;
}
