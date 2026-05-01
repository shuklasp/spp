<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SPPEntity\SPPEntity;

/**
 * Class LekhakNode
 * The base entity for all CMS content.
 */
class LekhakNode extends SPPEntity
{
    protected ?string $storage_strategy = 'flat';
    protected string $table = 'nodes';
    protected ?string $sequence = null;

    public function after_creation()
    {
        $this->storage_strategy = static::getMetadata('storage_strategy', 'flat');
    }

    /**
     * Override save to use the strategy driver.
     */
    public function save($strategy_override = true)
    {
        if ($strategy_override && $this->storage_strategy === 'dynamic') {
            return $this->getStorageDriver()->save($this);
        }
        return parent::save();
    }

    /**
     * Override load to use the strategy driver.
     */
    public function load($id)
    {
        if ($this->storage_strategy === 'dynamic') {
            return $this->getStorageDriver()->load($this, $id);
        }
        return parent::load($id);
    }

    protected function getStorageDriver()
    {
        if ($this->storage_strategy === 'dynamic') {
            return new \SPPMod\Lekhak\Storage\DynamicStorageDriver();
        }
        return new \SPPMod\Lekhak\Storage\FlatStorageDriver();
    }

    /**
     * Define default CMS attributes.
     */
    public function define_attributes()
    {
        return [
            'title' => 'varchar(255)',
            'alias' => 'varchar(255)',
            'body' => 'longtext',
            'author_id' => 'bigint',
            'status' => 'varchar(20)',
            'langcode' => 'varchar(10)',
            'translation_id' => 'bigint',
            'created' => 'datetime',
            'changed' => 'datetime'
        ];
    }
}
