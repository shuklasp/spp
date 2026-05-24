<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SPPEntity\SPPEntity;

/**
 * Class ContentType
 * Defines a bundle/type for Lekhak nodes.
 */
class ContentType extends SPPEntity
{
    protected string $table = 'content_types';

    public function define_attributes()
    {
        return [
            'name' => 'varchar(50)',
            'label' => 'varchar(255)',
            'description' => 'text',
            'storage_strategy' => 'varchar(20)',
            'is_revisionable' => 'tinyint(1)',
            'created' => 'datetime'
        ];
    }

    public function field_metadata()
    {
        return [
            'label' => [
                'label' => 'Display Name',
                'help' => 'The human-readable name for this content type (e.g. Article, Page).'
            ],
            'name' => [
                'label' => 'Machine Name',
                'help' => 'A unique identifier using only lowercase letters, numbers, and underscores.'
            ],
            'description' => [
                'label' => 'Description',
                'help' => 'Explain what this content type is used for.'
            ],
            'storage_strategy' => [
                'label' => 'Storage Strategy',
                'help' => 'Choose how fields are stored in the database (typically "flat").'
            ],
            'is_revisionable' => [
                'label' => 'Enable Revisions',
                'help' => 'Check this to keep a history of all changes to nodes of this type.'
            ]
        ];
    }
}
