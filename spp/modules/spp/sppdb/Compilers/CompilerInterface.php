<?php

namespace SPPMod\SPPDB\Compilers;

use SPPMod\SPPDB\SppEntityQuery;

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

    /**
     * Safely escapes a column or table identifier for the specific dialect.
     */
    public function escapeIdentifier(string $identifier): string;

    /**
     * Compile a CREATE TABLE DDL statement.
     */
    public function compileCreateTable(string $table, array $columns): string;

    /**
     * Compile an ADD COLUMN DDL statement.
     */
    public function compileAddColumns(string $table, array $columns): array;

    /**
     * Compile a vector similarity search clause.
     *
     * @param string $column Column name containing vector embeddings
     * @param array $queryVector Float array representing the target embedding
     * @param string $metric Distance metric: 'cosine', 'l2', or 'inner_product'
     * @param int $topK Maximum number of nearest neighbours
     * @return array{sql: string, bindings: array, orderBy: string, select: string, limit: int}
     */
    public function compileVectorSimilarity(string $column, array $queryVector, string $metric = 'cosine', int $topK = 10): array;
}
