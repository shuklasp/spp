<?php

namespace SPPMod\SPPEntity;

/**
 * Class SppEntityQuery
 * A chainable query builder for SPPEntity objects, supporting polymorphic dynamic fields.
 */
class SppEntityQuery
{
    protected string $entityClass;
    protected array $conditions = [];
    protected array $dynamicConditions = [];
    protected ?string $sort = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $withRelations = [];
    protected bool $withoutGlobalScopes = false;
    protected bool $withTrashed = false;
    protected bool $onlyTrashed = false;

    /**
     * @param string $entityClass The fully qualified class name of the entity.
     */
    public function __construct(string $entityClass)
    {
        if (!is_subclass_of($entityClass, '\\SPPMod\\SPPEntity\\SPPEntity')) {
            throw new \InvalidArgumentException("{$entityClass} must extend \\SPPMod\\SPPEntity\\SPPEntity");
        }
        $this->entityClass = $entityClass;
    }

    /**
     * Factory method for chaining.
     */
    public static function forEntity(string $entityClass): self
    {
        return new self($entityClass);
    }

    public function withoutGlobalScopes(): self
    {
        $this->withoutGlobalScopes = true;
        return $this;
    }

    public function withTrashed(): self
    {
        $this->withTrashed = true;
        return $this;
    }

    public function onlyTrashed(): self
    {
        $this->onlyTrashed = true;
        return $this;
    }

    /**
     * Add a condition for a base table column.
     *
     * @param string $field The column name.
     * @param mixed $value The value to compare against.
     * @param string $operator The comparison operator (e.g., '=', '>', 'LIKE').
     * @return $this
     */
    public function condition(string $field, $value, string $operator = '='): self
    {
        $validOperators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT', 'BETWEEN', 'NOT BETWEEN'];
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, $validOperators)) {
            $operator = '=';
        }
        $this->conditions[] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator
        ];
        return $this;
    }

    /**
     * Alias for condition
     */
    public function where(string $field, $value, string $operator = '='): self
    {
        return $this->condition($field, $value, $operator);
    }

    /**
     * Add a condition for a dynamic polymorphic field.
     *
     * @param string $field The dynamic field name.
     * @param mixed $value The value to compare against.
     * @param string $operator The comparison operator.
     * @return $this
     */
    public function dynamicCondition(string $field, $value, string $operator = '='): self
    {
        $validOperators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT', 'BETWEEN', 'NOT BETWEEN'];
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, $validOperators)) {
            $operator = '=';
        }
        $this->dynamicConditions[] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator
        ];
        return $this;
    }

    /**
     * Alias for dynamicCondition
     */
    public function whereDynamic(string $field, $value, string $operator = '='): self
    {
        return $this->dynamicCondition($field, $value, $operator);
    }

    /**
     * Set the sort order.
     *
     * @param string $field The field to sort by.
     * @param string $direction 'ASC' or 'DESC'.
     * @return $this
     */
    public function sort(string $field, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->sort = "{$field} {$direction}";
        return $this;
    }

    /**
     * Set the maximum number of results to return.
     *
     * @param int $limit
     * @param int|null $offset
     * @return $this
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = $limit;
        if ($offset !== null) {
            $this->offset = $offset;
        }
        return $this;
    }

    // --- AST Getters ---
    public function getConditions(): array { return $this->conditions; }
    public function getDynamicConditions(): array { return $this->dynamicConditions; }
    public function getSort(): ?string { return $this->sort; }
    public function getLimit(): ?int { return $this->limit; }
    public function getOffset(): ?int { return $this->offset; }

    /**
     * Magic method to handle Local Scopes on the entity class.
     * e.g., $query->active() -> SPPEntity::scopeActive($query)
     */
    public function __call(string $method, array $parameters)
    {
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this->entityClass, $scopeMethod)) {
            array_unshift($parameters, $this);
            $result = call_user_func_array([$this->entityClass, $scopeMethod], $parameters);
            return $result ?? $this;
        }
        throw new \BadMethodCallException("Call to undefined method {$this->entityClass}::{$method}()");
    }

    private int $rememberTtl = 0;

    /**
     * Set the relations to be eager-loaded.
     *
     * @param string|array $relations Name(s) of relations to preload
     * @return $this
     */
    public function with($relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();
        foreach ($relations as $rel) {
            $this->withRelations[] = $rel;
        }
        return $this;
    }

    /**
     * Cache the results of this query.
     *
     * @param int $ttl Time to live in seconds
     * @return $this
     */
    public function remember(int $ttl): self
    {
        $this->rememberTtl = $ttl;
        return $this;
    }

    /**
     * Paginate the query results.
     *
     * @param int $perPage Number of records per page
     * @param int $page The current page number
     * @return array Pagination data including the entities and metadata
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $db = new \SPPMod\SPPDB\SPPDB();

        // 1. Get the total count
        $countQuery = clone $this;
        $countQuery->limit = null;
        $countQuery->offset = null;
        
        $sqlData = $countQuery->buildQuery();
        // Replace SELECT base.* FROM with SELECT COUNT(*) as total FROM
        $sql = preg_replace('/^SELECT base\.\* FROM/i', 'SELECT COUNT(*) as total FROM', $sqlData['sql']);
        $values = $sqlData['values'];

        /** @var \SPPMod\SPPEntity\SPPEntity $entityInstance */
        $entityInstance = new $this->entityClass();
        $baseTable = $entityInstance->getTable();

        $countResult = $db->exec_squery($sql, $baseTable, $values);
        $total = empty($countResult) ? 0 : (int)$countResult[0]['total'];

        // 2. Fetch the data for the current page
        $this->limit($perPage, ($page - 1) * $perPage);
        $data = $this->execute();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max((int) ceil($total / $perPage), 1)
        ];
    }

    /**
     * Execute the query and return an array of instantiated entities.
     *
     * @return array
     */
    public function execute(): array
    {
        $db = new \SPPMod\SPPDB\SPPDB();

        $sqlData = $this->buildQuery();
        $sql = $sqlData['sql'];
        $values = $sqlData['values'];

        /** @var \SPPMod\SPPEntity\SPPEntity $entityInstance */
        $entityInstance = new $this->entityClass();
        $baseTable = $entityInstance->getTable();

        $cacheKey = null;
        if ($this->rememberTtl > 0 && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            $cacheKey = 'sppdb_query_' . md5($sql . serialize($values));
            $cached = \SPPMod\SPPCache\SPPCacheManager::get($cacheKey);
            if ($cached !== false) {
                return $this->hydrateFromRaw($cached);
            }
        }

        $result = $db->exec_squery($sql, $baseTable, $values);

        if ($cacheKey !== null) {
            $tag = $this->entityClass::getEntityName($this->entityClass) . '_list';
            \SPPMod\SPPCache\SPPCacheManager::set($cacheKey, $result, $this->rememberTtl, [$tag]);
        }

        return $this->hydrateFromRaw($result);
    }

    /**
     * Fetch records in memory-efficient chunks.
     *
     * @param int $count
     * @param callable $callback
     * @return bool
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;
        while (true) {
            $this->limit($count, ($page - 1) * $count);
            $results = $this->execute();
            if (empty($results)) {
                break;
            }
            if ($callback($results) === false) {
                return false;
            }
            $page++;
        }
        return true;
    }

    /**
     * Iterate over records lazily to minimize memory footprint.
     *
     * @return \Generator
     */
    public function cursor(): \Generator
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $sqlData = $this->buildQuery();
        $sql = $sqlData['sql'];
        $values = $sqlData['values'];

        /** @var \SPPMod\SPPEntity\SPPEntity $entityInstance */
        $entityInstance = new $this->entityClass();
        $baseTable = $entityInstance->getTable();

        $generator = $db->exec_squery_cursor($sql, $baseTable, $values);
        
        foreach ($generator as $row) {
            $entity = new $this->entityClass();
            $entity->setId($row[$entityInstance::getMetadata('id_field')]);
            foreach ($row as $attribute => $value) {
                if (!is_numeric($attribute)) {
                    $entity->set($attribute, $value);
                }
            }
            $entity->after_load();
            if (class_exists('\\SPP\\Core\\EventManager')) {
                \SPP\Core\EventManager::trigger('entity:after_load', $entity);
            }
            yield $entity;
        }
    }

    /**
     * Converts raw result array into Entities.
     */
    protected function hydrateFromRaw(array $result): array
    {
        /** @var \SPPMod\SPPEntity\SPPEntity $entityInstance */
        $entityInstance = new $this->entityClass();
        $entities = [];
        foreach ($result as $row) {
            /** @var \SPPMod\SPPEntity\SPPEntity $entity */
            $entity = new $this->entityClass();
            $entity->setId($row[$entityInstance::getMetadata('id_field')]);
            foreach ($row as $attribute => $value) {
                if (!is_numeric($attribute)) {
                    $entity->set($attribute, $value);
                }
            }
            $entities[] = $entity;
        }

        if (!empty($entities) && class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
            \SPPMod\SPPEntity\SppDynamicFieldHandler::loadFields($entities);
        }

        if (!empty($entities) && !empty($this->withRelations)) {
            $this->eagerLoadRelations($entities);
        }

        foreach ($entities as $entity) {
            $entity->after_load();
            if (class_exists('\\SPP\\Core\\EventManager')) {
                \SPP\Core\EventManager::trigger('entity:after_load', $entity);
            }
        }

        return $entities;
    }

    /**
     * Pre-loads relations for the given entities to avoid N+1 queries.
     * @param array $entities
     */
    protected function eagerLoadRelations(array $entities): void
    {
        $rels = \SPP\Registry::get('EntityRelations');
        if (!is_array($rels)) {
            return;
        }

        $ids = array_map(function($e) { return $e->getId(); }, $entities);
        $entitiesById = [];
        foreach ($entities as $e) {
            $entitiesById[$e->getId()] = $e;
        }

        foreach ($this->withRelations as $relName) {
            foreach ($rels as $rel) {
                if ($rel['name'] === $relName && ($rel['parent_entity'] === $this->entityClass || $rel['child_entity'] === $this->entityClass)) {
                    
                    if ($rel['relation_type'] === 'OneToMany' && $rel['parent_entity'] === $this->entityClass) {
                        $childClass = $rel['child_entity'];
                        $childField = $rel['child_entity_field'];
                        $children = $childClass::query()->condition($childField, $ids, 'IN')->execute();
                        
                        // Group children by parent id
                        $grouped = [];
                        foreach ($children as $child) {
                            $parentId = $child->get($childField);
                            $grouped[$parentId][] = $child;
                        }
                        // Assign to parents
                        foreach ($entitiesById as $id => $parent) {
                            $parent->setRelatedCache($relName, $grouped[$id] ?? []);
                        }
                    }

                    if ($rel['relation_type'] === 'ManyToOne' && $rel['child_entity'] === $this->entityClass) {
                        $parentClass = $rel['parent_entity'];
                        $parentField = $rel['parent_entity_field'];
                        $childField = $rel['child_entity_field']; // the FK on this entity
                        
                        $parentIdsToFetch = [];
                        foreach ($entities as $e) {
                            $pid = $e->get($childField);
                            if ($pid) $parentIdsToFetch[] = $pid;
                        }
                        $parentIdsToFetch = array_unique($parentIdsToFetch);
                        
                        if (!empty($parentIdsToFetch)) {
                            $parents = $parentClass::query()->condition($parentField, $parentIdsToFetch, 'IN')->execute();
                            $parentsById = [];
                            foreach ($parents as $p) {
                                $parentsById[$p->get($parentField)] = $p;
                            }
                            
                            foreach ($entities as $e) {
                                $pid = $e->get($childField);
                                $e->setRelatedCache($relName, $pid && isset($parentsById[$pid]) ? $parentsById[$pid] : null);
                            }
                        }
                    }

                    if ($rel['relation_type'] === 'OneToOne') {
                        if ($rel['parent_entity'] === $this->entityClass) {
                            $childClass = $rel['child_entity'];
                            $childField = $rel['child_entity_field'];
                            $children = $childClass::query()->condition($childField, $ids, 'IN')->execute();
                            
                            $mapped = [];
                            foreach ($children as $child) {
                                $parentId = $child->get($childField);
                                $mapped[$parentId] = $child;
                            }
                            foreach ($entitiesById as $id => $parent) {
                                $parent->setRelatedCache($relName, $mapped[$id] ?? null);
                            }
                        } else {
                            $parentClass = $rel['parent_entity'];
                            $parentField = $rel['parent_entity_field'];
                            $childField = $rel['child_entity_field'];
                            
                            $parentIdsToFetch = [];
                            foreach ($entities as $e) {
                                $pid = $e->get($childField);
                                if ($pid) $parentIdsToFetch[] = $pid;
                            }
                            $parentIdsToFetch = array_unique($parentIdsToFetch);
                            
                            if (!empty($parentIdsToFetch)) {
                                $parents = $parentClass::query()->condition($parentField, $parentIdsToFetch, 'IN')->execute();
                                $parentsById = [];
                                foreach ($parents as $p) {
                                    $parentsById[$p->get($parentField)] = $p;
                                }
                                
                                foreach ($entities as $e) {
                                    $pid = $e->get($childField);
                                    $e->setRelatedCache($relName, $pid && isset($parentsById[$pid]) ? $parentsById[$pid] : null);
                                }
                            }
                        }
                    }

                    if ($rel['relation_type'] === 'MorphMany' && $rel['parent_entity'] === $this->entityClass) {
                        $childClass = $rel['child_entity'];
                        $morphPrefix = $rel['morph_prefix'];
                        $children = $childClass::query()
                            ->condition($morphPrefix . '_id', $ids, 'IN')
                            ->condition($morphPrefix . '_type', $this->entityClass)
                            ->execute();
                        
                        $grouped = [];
                        foreach ($children as $child) {
                            $parentId = $child->get($morphPrefix . '_id');
                            $grouped[$parentId][] = $child;
                        }
                        foreach ($entitiesById as $id => $parent) {
                            $parent->setRelatedCache($relName, $grouped[$id] ?? []);
                        }
                    }

                    break; // Move to next relation in $this->withRelations
                }
            }
        }
    }

    /**
     * Alias for execute()
     */
    public function get(): array
    {
        return $this->execute();
    }

    /**
     * Lock the selected rows in the table for updating.
     */
    public function lockForUpdate()
    {
        $this->lockMode = 'update';
        return $this;
    }

    /**
     * Share lock the selected rows in the table.
     */
    public function sharedLock()
    {
        $this->lockMode = 'shared';
        return $this;
    }

    public function getLockMode(): ?string
    {
        return $this->lockMode;
    }

    /**
     * Internal method to build the AST and compile the SQL query string and values.
     *
     * @return array ['sql' => string, 'values' => array]
     */
    protected function buildQuery(): array
    {
        // Apply Global Scopes
        if (!$this->withoutGlobalScopes) {
            $scopes = $this->entityClass::getGlobalScopes();
            foreach ($scopes as $scope) {
                if ($scope instanceof \Closure) {
                    $scope($this);
                }
            }
        }

        // Apply Soft Delete Filter
        $db = new \SPPMod\SPPDB\SPPDB();
        if (!$db->isXDB() && $this->entityClass::getMetadata('soft_delete', false)) {
            if ($this->onlyTrashed) {
                $this->condition('deleted_at', null, 'IS NOT');
            } elseif (!$this->withTrashed) {
                $this->condition('deleted_at', null, 'IS');
            }
        }

        /** @var \SPPMod\SPPEntity\SPPEntity $entityInstance */
        $entityInstance = new $this->entityClass();
        $baseTable = $entityInstance->getTable();

        $compiler = $db->getCompiler();
        return $compiler->compile($this, $baseTable, $this->entityClass);
    }

    /**
     * Get the generated SQL query string.
     */
    public function toSql(): string
    {
        return $this->buildQuery()['sql'];
    }

    /**
     * Get the bound values for the query.
     */
    public function getBindings(): array
    {
        return $this->buildQuery()['values'];
    }
}
