<?php
namespace App\Lekhak\Entities;

use SPPMod\SPPEntity\SPPEntity;

/**
 * Class ContentType
 * Defines a content type (bundle) for nodes.
 */
class ContentType extends SPPEntity
{
    protected string $table = 'content_types';

    public function define_attributes()
    {
        return [
            'name' => 'varchar(50)', // Machine name
            'label' => 'varchar(255)',
            'description' => 'text',
            'storage_strategy' => 'varchar(20)', // flat or dynamic
            'help_text' => 'text'
        ];
    }

    public function field_metadata()
    {
        return [
            'name' => [
                'label' => 'Machine Name',
                'help' => 'A unique identifier using only lowercase letters, numbers, and underscores.'
            ],
            'label' => [
                'label' => 'Display Name',
                'help' => 'The human-readable name for this content type (e.g. Article, Page).'
            ],
            'description' => [
                'label' => 'Description',
                'help' => 'Explain what this content type is used for.'
            ],
            'storage_strategy' => [
                'label' => 'Storage Strategy',
                'type' => 'radio',
                'options' => [
                    'flat' => 'Flat Table (Recommended)',
                    'dynamic' => 'Dynamic Properties (JSON)'
                ],
                'help' => 'Choose how fields are stored in the database.'
            ],
            'help_text' => [
                'label' => 'Default Help Text',
                'help' => 'A message that will be shown to content creators by default.'
            ]
        ];
    }
}
