<?php
namespace App\Lekhak\Entities;

use SPPMod\SppDb\SPPEntity;

/**
 * Class Vocabulary
 * Defines a taxonomy vocabulary grouping for terms.
 */
class Vocabulary extends SPPEntity
{
    protected string $table = 'vocabularies';

    public function define_attributes()
    {
        return [
            'name' => 'varchar(50)', // Machine name (e.g. "tags")
            'label' => 'varchar(255)', // Human-readable name (e.g. "Tags")
            'description' => 'text'
        ];
    }
}
