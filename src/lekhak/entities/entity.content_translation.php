<?php
namespace App\Lekhak\Entities;

use SPPMod\SppDb\SPPEntity;

/**
 * Class ContentTranslation
 * Defines content translations for entities.
 */
class ContentTranslation extends SPPEntity
{
    protected string $table = 'content_translation';

    public function define_attributes()
    {
        return [
            'entity_type' => 'varchar(50)',
            'entity_id' => 'varchar(50)',
            'langcode' => 'varchar(10)',
            'field_name' => 'varchar(100)',
            'translated_value' => 'text'
        ];
    }
}
