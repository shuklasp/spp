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
        $this->conditions[] = [
            'field' => $field,
            'value' => $value,
            'operator' => strtoupper($operator)
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
        $this->dynamicConditions[] = [
            'field' => $field,
            'value' => $value,
            'operator' => strtoupper($operator)
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

        $result = $db->exec_squery($sql, $baseTable, $values);

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
            \SPP\Core\EventManager::trigger('entity:after_load', $entity);
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
     * Build the query
     */
    protected function buildQuery(): array
    {
        /** @var \SPPMod\SPPEntity\SPPEntity $entityInstance */
        $entityInstance = new $this->entityClass();
        $baseTable = $entityInstance->getTable();
        $dynamicTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_fields');

        $sql = "SELECT base.* FROM {$baseTable} base ";

        $joinIndex = 0;
        $values = [];
        $whereClauses = [];

        // Base table conditions
        foreach ($this->conditions as $cond) {
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', $cond['field']);
            $operator = $cond['operator'];

            if (in_array($operator, ['IN', 'NOT IN']) && is_array($cond['value'])) {
                $placeholders = implode(',', array_fill(0, count($cond['value']), '?'));
                $whereClauses[] = "base.{$field} {$operator} ({$placeholders})";
                $values = array_merge($values, $cond['value']);
            } else {
                $whereClauses[] = "base.{$field} {$operator} ?";
                $values[] = $cond['value'];
            }
        }

        // Dynamic table conditions (joins)
        if (!empty($this->dynamicConditions) && class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
            foreach ($this->dynamicConditions as $cond) {
                $joinAlias = "dyn" . $joinIndex;

                $field = $cond['field'];
                $value = $cond['value'];
                $operator = $cond['operator'];

                // Determine value column based on type
                $col = is_int($value) ? 'value_int' : (is_float($value) ? 'value_decimal' : 'value_string');

                $sql .= " INNER JOIN {$dynamicTable} {$joinAlias} ON base.id = {$joinAlias}.entity_id AND {$joinAlias}.entity_type = ? AND {$joinAlias}.field_name = ? ";
                $values[] = ltrim($this->entityClass, '\\');
                $values[] = $field;

                if (in_array($operator, ['IN', 'NOT IN']) && is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $whereClauses[] = "{$joinAlias}.{$col} {$operator} ({$placeholders})";
                    $values = array_merge($values, $value);
                } else {
                    $whereClauses[] = "{$joinAlias}.{$col} {$operator} ?";
                    $values[] = $value;
                }

                $joinIndex++;
            }
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        if ($this->sort) {
            $sql .= " ORDER BY base." . preg_replace('/[^a-zA-Z0-9_\s]/', '', $this->sort);
        }

        if ($this->limit) {
            $sql .= " LIMIT " . (int)$this->limit;
            if ($this->offset) {
                $sql .= " OFFSET " . (int)$this->offset;
            }
        }

        return ['sql' => $sql, 'values' => $values];
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
