<?php

namespace SPPMod\SppDb;

use SPPMod\SPPDB\SPPDB;

/**
 * Class SPPInterDB
 * Federated Data Aggregation and GraphQL Gateway Module.
 */
class SPPInterDB
{
    private array $registry = [];
    private string $mode = 'interdb'; // 'interdb' or 'standalone'

    public function __construct()
    {
        $this->mode = \SPP\Module::getConfig('mode', 'sppinterdb') ?: 'interdb';
        $this->loadRegistry();
    }

    private function loadRegistry()
    {
        // Load entity-to-adapter mappings from config
        $this->registry = \SPP\Module::getConfig('mappings', 'sppinterdb') ?: [];
    }

    /**
     * Register a mapping programmatically.
     */
    public function map(string $typeName, string $engine, string $table)
    {
        $this->registry[$typeName] = [
            'engine' => $engine,
            'table' => $table
        ];
    }

    /**
     * Execute a GraphQL query across the bridged databases.
     */
    public function graphql(string $query, array $variables = []): array
    {
        // Simple regex parser for federation
        if (preg_match('/([a-z0-9_]+)\s*\((.+?)\)\s*{(.+)}/is', $query, $matches)) {
            $rootField = trim($matches[1]);
            $argsStr = trim($matches[2]);
            $selectionSet = trim($matches[3]);

            return $this->resolve($rootField, $argsStr, $selectionSet, $variables);
        }

        return ['errors' => ['Invalid GraphQL syntax']];
    }

    private function resolve(string $field, string $args, string $selectionSet, array $variables): array
    {
        $mapping = $this->registry[$field] ?? null;

        // In standalone mode, we default to the primary DB if no mapping exists
        if (!$mapping && $this->mode === 'standalone') {
            $mapping = ['engine' => 'default', 'table' => $field];
        }

        if (!$mapping) {
            return ['errors' => ["Field '{$field}' not found in InterDB registry"]];
        }

        // Initialize adapter via SPPDB
        $db = $this->getDatabase($mapping['engine']);

        // Extract ID (simplified)
        preg_match('/id\s*:\s*([0-9]+)/', $args, $argMatches);
        $id = $argMatches[1] ?? null;

        if (!$id) {
            return ['errors' => ['ID argument required']];
        }

        // Fetch
        $data = $db->table($mapping['table'])->where('id', $id)->first();
        if (!$data) {
            return ['data' => null];
        }

        // Resolve Selection Set
        $result = [];
        $lines = explode("\n", $selectionSet);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Nested relationship?
            if (preg_match('/([a-z0-9_]+)\s*{(.+)}/is', $line, $nestedMatches)) {
                $nestedField = $nestedMatches[1];
                $nestedSelectionSet = $nestedMatches[2];

                // Federated Stitching: Potentially different DB
                $result[$nestedField] = $this->resolveNested($nestedField, $data['id'], $nestedSelectionSet);
            } else {
                if (isset($data[$line])) {
                    $result[$line] = $data[$line];
                }
            }
        }

        return ['data' => [$field => $result]];
    }

    private function resolveNested(string $field, $parentId, string $selectionSet): array
    {
        $mapping = $this->registry[$field] ?? null;
        if (!$mapping && $this->mode === 'standalone') {
            $mapping = ['engine' => 'default', 'table' => $field];
        }

        if (!$mapping) {
            return [];
        }

        $db = $this->getDatabase($mapping['engine']);

        // Cross-DB lookup
        $nestedData = $db->table($mapping['table'])->where('user_id', $parentId)->first();
        if (!$nestedData) {
            return [];
        }

        $result = [];
        $fields = explode(' ', str_replace(['{', '}', "\n", "\r"], '', $selectionSet));
        foreach ($fields as $f) {
            $f = trim($f);
            if (empty($f)) {
                continue;
            }
            if (isset($nestedData[$f])) {
                $result[$f] = $nestedData[$f];
            }
        }

        return $result;
    }

    public function get_entities(): array
    {
        $entities = [];
        foreach ($this->registry as $name => $meta) {
            $entities[] = [
                'name' => $name,
                'engine' => $meta['engine'],
                'table' => $meta['table']
            ];
        }
        return ['success' => true, 'entities' => $entities];
    }

    public function get_schema(array $params): array
    {
        $entity = $params['entity'] ?? null;
        if (!$entity || !isset($this->registry[$entity])) {
            return ['success' => false, 'message' => "Entity '{$entity}' not found."];
        }

        $mapping = $this->registry[$entity];
        $db = $this->getDatabase($mapping['engine']);

        try {
            $table = $mapping['table'];
            // Dynamic schema discovery (MySQL implementation example)
            $columns = $db->query("SHOW COLUMNS FROM `{$table}`");
            $fields = [];
            foreach ($columns as $col) {
                $fields[] = [
                    'name' => $col['Field'],
                    'type' => $col['Type'],
                    'nullable' => $col['Null'] === 'YES'
                ];
            }
            return ['success' => true, 'entity' => $entity, 'fields' => $fields];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function getDatabase(string $engine): SPPDB
    {
        if ($engine === 'default' || $this->mode === 'standalone') {
            return new SPPDB();
        }

        return new SPPDB("{$engine}:dbname=default");
    }
}
