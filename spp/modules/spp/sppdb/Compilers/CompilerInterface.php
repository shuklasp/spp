<?php

namespace SPPMod\SppDb\Compilers;

use SPPMod\SppDb\SppEntityQuery;

/**
 * Interface CompilerInterface
 * Defines the contract for SQL dialect compilers.
 */
interface CompilerInterface
{
    /**
     * Compile the AST from the query into a dialect-specific SQL statement and bindings.
     *
     * @param SppEntityQuery $query
     * @param string $baseTable
     * @param string $entityClass
     * @return array ['sql' => string, 'values' => array]
     */
    public function compile(SppEntityQuery $query, string $baseTable, string $entityClass): array;

    /**
     * Compile the lock string (e.g. FOR UPDATE) based on the query lock mode.
     */
    public function compileLock(SppEntityQuery $query): string;
}
