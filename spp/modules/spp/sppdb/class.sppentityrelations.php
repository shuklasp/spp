<?php

namespace SPPMod\SPPDB;

/**
 * class SPPEntityRelations
 * Defines the relations between entities
 * @author  Satya Prakash Shukla
 * @version 1.0
 * @since   2015-09-15
 */
class SPPEntityRelations
{
    protected static $parent_to_child = [];
    protected static $child_to_parent = [];

    /**
     * Helper to resolve, validate, and retrieve relation configuration
     * @param string $relation
     * @return array
     * @throws \SPP\SPPException
     */
    protected static function _resolveRelation(string $relation)
    {
        $parts = explode('=>', $relation);
        $parent_entity = trim($parts[0] ?? '');
        $child_entity = trim($parts[1] ?? '');

        if (!SPPEntity::entityExists($parent_entity) || !SPPEntity::entityExists($child_entity)) {
            throw new \SPP\SPPException('Invalid entity class in relation: ' . $relation);
        }

        if (!isset(self::$parent_to_child[$parent_entity]) || !in_array($child_entity, self::$parent_to_child[$parent_entity])) {
            throw new \SPP\SPPException('No registered sub-relation for ' . $relation . ' found');
        }

        $rel_record = \SPP\Registry::get('EntityRelations');
        if (is_array($rel_record)) {
            foreach ($rel_record as $rel) {
                if ($rel['parent_entity'] == $parent_entity && $rel['child_entity'] == $child_entity) {
                    return $rel;
                }
            }
        }

        throw new \SPP\SPPException('No relation schema found for ' . $relation);
    }

    /****
     * public static function registerEntityRelation()
     * Registers the relations between entities
     *
     * @param string $parent_entity
     * @param string $parent_entity_field
     * @param string $child_entity
     * @param string $child_entity_field
     * @param string $relation_type
     * @throws \SPP\SPPException
     */
    public static function registerEntityRelation(
        ?string $relation_name,
        string $parent_entity,
        string $parent_entity_field,
        string $child_entity,
        string $child_entity_field,
        string $relation_type
    ) {
        $rel_array = [
            'name' => $relation_name,
            'parent_entity' => $parent_entity,
            'parent_entity_field' => $parent_entity_field,
            'child_entity' => $child_entity,
            'child_entity_field' => $child_entity_field,
            'relation_type' => $relation_type,
            'morph_prefix' => null // Set if MorphTo/MorphMany
        ];

        if (!SPPEntity::entityExists($parent_entity)) {
            throw new \SPP\SPPException("Invalid parent entity class " . $parent_entity . " found");
        }
        if (!SPPEntity::entityExists($child_entity)) {
            throw new \SPP\SPPException("Invalid child entity class" . $child_entity . " found");
        }

        $prev_rel = [];
        if (\SPP\Registry::isRegistered('EntityRelations')) {
            $prev_rel = \SPP\Registry::get('EntityRelations');
            if (is_array($prev_rel) && !in_array($rel_array, $prev_rel)) {
                $prev_rel[] = $rel_array;
            }
        } else {
            $prev_rel[] = $rel_array;
        }
        \SPP\Registry::register('EntityRelations', $prev_rel);

        if (!isset(self::$parent_to_child[$parent_entity])) {
            self::$parent_to_child[$parent_entity] = [];
        }
        if (!in_array($child_entity, self::$parent_to_child[$parent_entity])) {
            self::$parent_to_child[$parent_entity][] = $child_entity;
        }

        if (!isset(self::$child_to_parent[$child_entity])) {
            self::$child_to_parent[$child_entity] = [];
        }
        if (!in_array($parent_entity, self::$child_to_parent[$child_entity])) {
            self::$child_to_parent[$child_entity][] = $parent_entity;
        }

        $parent = new $parent_entity();
        if (!$parent->attributeExists($parent_entity_field)) {
            $parent::addAttributes([$parent_entity_field => 'varchar(20)']);
        }
        $child = new $child_entity();
        if (!$child->attributeExists($child_entity_field)) {
            $child::addAttributes([$child_entity_field => 'varchar(20)']);
        }
    }

    /**
     * Registers a Polymorphic MorphMany relation.
     */
    public static function registerMorphMany(
        string $relation_name,
        string $parent_entity,
        string $child_entity,
        string $morph_prefix
    ) {
        $rel_array = [
            'name' => $relation_name,
            'parent_entity' => $parent_entity,
            'parent_entity_field' => 'id',
            'child_entity' => $child_entity,
            'child_entity_field' => $morph_prefix . '_id',
            'relation_type' => 'MorphMany',
            'morph_prefix' => $morph_prefix
        ];

        $prev_rel = \SPP\Registry::isRegistered('EntityRelations') ? \SPP\Registry::get('EntityRelations') : [];
        if (!in_array($rel_array, $prev_rel)) {
            $prev_rel[] = $rel_array;
        }
        \SPP\Registry::register('EntityRelations', $prev_rel);

        $child = new $child_entity();
        $fieldsToAdd = [];
        if (!$child->attributeExists($morph_prefix . '_id')) {
            $fieldsToAdd[$morph_prefix . '_id'] = 'varchar(64)';
        }
        if (!$child->attributeExists($morph_prefix . '_type')) {
            $fieldsToAdd[$morph_prefix . '_type'] = 'varchar(255)';
        }
        if (!empty($fieldsToAdd)) {
            $child::addAttributes($fieldsToAdd);
        }
    }

    /**
     * public static function getChildren(string $parent_entity)
     * @param string $parent_entity
     * @return array
     * @throws \SPP\SPPException
     */
    public static function getChildren(string $parent_entity)
    {
        return self::$parent_to_child[$parent_entity] ?? [];
    }

    /**
     * public static function getParents(string $child_entity)
     * @param string $child_entity
     * @return array
     * @throws \SPP\SPPException
     */
    public static function getParents(string $child_entity)
    {
        return self::$child_to_parent[$child_entity] ?? [];
    }

    /****
     * public static function getRelations()
     * @return array
     */
    public static function getRelations()
    {
        return self::$parent_to_child;
    }

    /**
     * Resolves and fetches related entities for a given instance and relation name.
     */
    public static function getRelated($entity, string $name)
    {
        $class = get_class($entity);
        $rels = \SPP\Registry::get('EntityRelations');
        if (!is_array($rels)) {
            return null;
        }

        foreach ($rels as $rel) {
            if ($rel['name'] === $name && ($rel['parent_entity'] === $class || $rel['child_entity'] === $class)) {

                // If it's a OneToMany where we are the parent
                if ($rel['relation_type'] === 'OneToMany' && $rel['parent_entity'] === $class) {
                    return self::getRelatedEntitiesByParent(
                        $rel['parent_entity'] . '=>' . $rel['child_entity'],
                        $entity->getId()
                    );
                }

                // If it's a ManyToOne (belongsTo) where we are the child
                if ($rel['relation_type'] === 'ManyToOne' && $rel['child_entity'] === $class) {
                    $results = self::getRelatedEntitiesByChild(
                        $rel['parent_entity'] . '=>' . $rel['child_entity'],
                        $entity->getId()
                    );
                    return count($results) > 0 ? $results[0] : null;
                }

                // If it's a OneToOne
                if ($rel['relation_type'] === 'OneToOne') {
                    if ($rel['parent_entity'] === $class) {
                        $results = self::getRelatedEntitiesByParent(
                            $rel['parent_entity'] . '=>' . $rel['child_entity'],
                            $entity->getId()
                        );
                    } else {
                        $results = self::getRelatedEntitiesByChild(
                            $rel['parent_entity'] . '=>' . $rel['child_entity'],
                            $entity->getId()
                        );
                    }
                    return count($results) > 0 ? $results[0] : null;
                }

                // If it's a MorphMany where we are the parent
                if ($rel['relation_type'] === 'MorphMany' && $rel['parent_entity'] === $class) {
                    $childClass = $rel['child_entity'];
                    $morphPrefix = $rel['morph_prefix'];
                    return $childClass::query()
                        ->condition($morphPrefix . '_id', $entity->getId())
                        ->condition($morphPrefix . '_type', $class)
                        ->execute();
                }

                // If it's a MorphTo where we are the child (implicit reverse of MorphMany)
                // Note: To implement full generic MorphTo, the entity handles it dynamically.
            }
        }

        // Generic MorphTo fallback if relation name matches $morphPrefix
        // Example: $comment->getRelated('commentable')
        $typeField = $name . '_type';
        $idField = $name . '_id';
        if ($entity->attributeExists($typeField) && $entity->attributeExists($idField)) {
            $parentClass = $entity->get($typeField);
            $parentId = $entity->get($idField);
            if ($parentClass && class_exists($parentClass) && $parentId) {
                return new $parentClass($parentId);
            }
        }

        return null;
    }

    /****
     * public static function getRelatedEntitiesByParent()
     * Returns an array of related entities for a given parent entity
     *
     * @param string $relation
     * @param $parent_id
     * @param array $attributes
     * @param array $values
     * @return array
     */
    public static function getRelatedEntitiesByParent(string $relation, $parent_id, $attributes = [], $values = [])
    {
        $rel = self::_resolveRelation($relation);
        $child_ent = new $rel['child_entity']();
        return $child_ent->loadMultiple(
            array_merge([$rel['child_entity_field']], $attributes),
            array_merge([$parent_id], $values)
        );
    }

    /****
     * public static function getRelatedEntitiesByChild()
     * Returns an array of related entities for a given child entity
     *
     * @param string $relation
     * @param $child_id
     * @param array $attributes
     * @param array $values
     * @return array
     */
    public static function getRelatedEntitiesByChild(string $relation, $child_id, $attributes = [], $values = [])
    {
        $rel = self::_resolveRelation($relation);

        $child_ent = new $rel['child_entity']($child_id);
        $mapped_id = $child_ent->get($rel['child_entity_field']);

        if (empty($mapped_id)) {
            return [];
        }

        $parent_ent = new $rel['parent_entity']();
        return $parent_ent->loadMultiple(
            array_merge([$rel['parent_entity_field']], $attributes),
            array_merge([$mapped_id], $values)
        );
    }

    /****
     * public static function relateEntities()
     * Relate two existing entity objects already registered in related entities array
     *
     * @param string $relation
     * @param $parent_id
     * @param $child_id
     * @return boolean
     * @throws \SPP\SPPException
     */
    public static function relateEntities(string $relation, $parent_id, $child_id)
    {
        $rel = self::_resolveRelation($relation);
        $field = $rel['child_entity_field'];

        $child_ent = new $rel['child_entity']($child_id);
        $child_ent->$field = $parent_id;
        $child_ent->save();
    }

    /****
     * public static function unrelateEntities()
     * Unrelate two entities
     *
     * @param string $relation
     * @param $parent_id
     * @param $child_id
     * @return boolean
     * @throws \SPP\SPPException
     *
     *  */
    public static function unrelateEntities(string $relation, $parent_id, $child_id)
    {
        $rel = self::_resolveRelation($relation);
        $field = $rel['child_entity_field'];

        $child_ent = new $rel['child_entity']($child_id);
        $child_ent->$field = null;
        $child_ent->save();
    }

    /**
     * public static function addChildEntity()
     * Add a child entity to a parent entity
     *
     * @param string $relation
     * @param $parent_id
     * @param $attributes
     * @return boolean
     * @throws \SPP\SPPException
     *
     */
    public static function addChildEntity(string $relation, $parent_id, $attributes)
    {
        $rel = self::_resolveRelation($relation);
        $field = $rel['child_entity_field'];

        $child_ent = new $rel['child_entity']();
        $child_ent->$field = $parent_id;
        $child_ent->setAttributes($attributes);
        return $child_ent->save();
    }

    // --- HIERARCHY HYBRID LOGIC METHODS ---

    /**
     * public static function getAncestors()
     * Crawl up the hierarchy tree.
     */
    public static function getAncestors(string $relation, $id, int $limit = 0)
    {
        $rel = self::_resolveRelation($relation);
        if ($rel['parent_entity'] !== $rel['child_entity']) {
            throw new \SPP\SPPException("Relation must be self-referential (Parent=>Parent) for hierarchy ancestors.");
        }

        $ancestors = [];
        $current_id = $id;
        $depth = 0;

        while ($current_id !== null && ($limit === 0 || $depth < $limit)) {
            $child = new $rel['child_entity']($current_id);
            $parent_id = $child->get($rel['child_entity_field']);

            if (empty($parent_id) || $parent_id == $current_id) { // Prevent infinite self-loops
                break;
            }

            $parent = new $rel['parent_entity']($parent_id);
            $ancestors[] = $parent;
            $current_id = $parent_id;
            $depth++;
        }
        return $ancestors;
    }

    /**
     * public static function yieldDescendants()
     * Yield descendants safely using memory-efficient Generators.
     */
    public static function yieldDescendants(string $relation, $id, int $max_depth = 0)
    {
        $rel = self::_resolveRelation($relation);
        if ($rel['parent_entity'] !== $rel['child_entity']) {
            throw new \SPP\SPPException("Relation must be self-referential for hierarchy descendants.");
        }

        $queue = [['id' => $id, 'depth' => 1]];
        $visited = [$id => true];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $current_id = $current['id'];
            $depth = $current['depth'];

            if ($max_depth > 0 && $depth > $max_depth) {
                continue;
            }

            $node_shell = new $rel['child_entity']();
            $children = $node_shell->loadMultiple([$rel['child_entity_field']], [$current_id]);

            foreach ($children as $child) {
                $c_id = $child->getId();
                if (!isset($visited[$c_id])) {
                    $visited[$c_id] = true;
                    yield $child;
                    $queue[] = ['id' => $c_id, 'depth' => $depth + 1];
                }
            }
        }
    }

    /**
     * public static function getDescendants()
     * Retrieve all descendants using hybrid flat or tree array configurations.
     */
    public static function getDescendants(string $relation, $id, string $format = 'flat', int $max_depth = 0)
    {
        if ($format === 'flat') {
            $results = [];
            // PHP 7+ generator extraction
            foreach (self::yieldDescendants($relation, $id, $max_depth) as $descendant) {
                $results[] = $descendant;
            }
            return $results;
        } elseif ($format === 'tree') {
            $rel = self::_resolveRelation($relation);
            return self::_buildTreeBranch($rel, $id, 1, $max_depth);
        }

        throw new \SPP\SPPException("Unknown format " . $format);
    }

    protected static function _buildTreeBranch($rel, $current_id, $depth, $max_depth)
    {
        if ($max_depth > 0 && $depth > $max_depth) {
            return [];
        }

        $branch = [];
        $node_shell = new $rel['child_entity']();
        $children = $node_shell->loadMultiple([$rel['child_entity_field']], [$current_id]);

        foreach ($children as $child) {
            $node = ['entity' => $child];
            $nested = self::_buildTreeBranch($rel, $child->getId(), $depth + 1, $max_depth);
            if (!empty($nested)) {
                $node['children'] = $nested;
            }
            $branch[] = $node;
        }
        return $branch;
    }
}
