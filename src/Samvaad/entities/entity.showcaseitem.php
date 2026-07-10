<?php
namespace App\Samvaad\Entities;

use SPPMod\SPPDB\SPPEntity;

class ShowcaseItem extends SPPEntity
{
    public static function getTableName(): string
    {
        return 'showcase_items';
    }

    public static function getMetadata(string $key = '', $default = null)
    {
        $meta = [
            'table' => self::getTableName(),
            'id_field' => 'id',
            'sequence' => 'showcase_items_seq',
            'key_type' => 'int',
            'enable_api' => true,
            'attributes' => [
                'title' => 'varchar(255)',
                'description' => 'text',
                'status' => 'varchar(50)',
                'created_at' => 'datetime'
            ],
            'searchable' => ['title', 'description']
        ];
        return $key ? ($meta[$key] ?? $default) : $meta;
    }
}
