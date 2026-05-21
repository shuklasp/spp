<?php
namespace App\Lekhak\Entities;

use SPPMod\SPPEntity\SPPEntity;

/**
 * Class ViewDefinition
 * Defines a view configuration (fields, filters, sorting).
 */
class ViewDefinition extends SPPEntity
{
    protected string $table = 'view_definitions';

    public function define_attributes()
    {
        return [
            'name' => 'varchar(100)',
            'label' => 'varchar(255)',
            'base_table' => 'varchar(100)', // e.g., 'nodes', 'users'
            'fields' => 'text', // JSON array of fields to select
            'filters' => 'text', // JSON array of filter conditions
            'sorts' => 'text', // JSON array of sort criteria
            'pagination' => 'int', // Items per page
            'display_format' => 'varchar(50)' // 'table', 'grid', 'list'
        ];
    }
}
