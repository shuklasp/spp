<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SPPEntity\SPPEntity;

/**
 * Class LandingBlock
 * Represents a content block within a landing page.
 */
class LandingBlock extends SPPEntity
{
    protected string $table = 'landing_blocks';

    public function define_attributes()
    {
        return [
            'page_id' => 'bigint',
            'block_type' => 'varchar(50)',
            'data' => 'longtext', // JSON data for block content
            'weight' => 'int',
            'region' => 'varchar(50)',
            'created' => 'datetime'
        ];
    }

    /**
     * Helper to decode JSON data.
     */
    public function getContent(): array
    {
        return json_decode($this->data ?? '{}', true);
    }

    /**
     * Helper to set JSON data.
     */
    public function setContent(array $content): void
    {
        $this->data = json_encode($content);
    }

    /**
     * Resolve dynamic entities based on block configuration.
     */
    public function resolveEntities(): array
    {
        $content = $this->getContent();
        if ($this->block_type !== 'dynamic_list') return [];

        $entityType = $content['entity_type'] ?? 'node';
        $conditions = $content['conditions'] ?? [];
        $limit = $content['limit'] ?? 5;
        $sort = $content['sort'] ?? 'created DESC';

        $class = match($entityType) {
            'node' => LekhakNode::class,
            'user' => \SPPMod\SPPAuth\User::class,
            'type' => ContentType::class,
            default => LekhakNode::class
        };

        if (method_exists($class, 'find_all')) {
            return $class::find_all($conditions, $sort, $limit);
        }

        return [];
    }
}
