<?php
namespace App\Lekhak\Entities;

use SPPMod\SPPEntity\SPPEntity;

/**
 * Class Field
 * Defines a field attached to a content type.
 */
class Field extends SPPEntity
{
    protected string $table = 'fields';

    public function define_attributes()
    {
        return [
            'bundle' => 'varchar(50)', // The content type machine name
            'field_name' => 'varchar(50)', // Machine name of the field
            'label' => 'varchar(255)',
            'type' => 'varchar(50)', // text, integer, image, etc.
            'settings' => 'text', // JSON settings
            'required' => 'boolean',
            'weight' => 'int'
        ];
    }
    public function field_metadata()
    {
        return [
            'bundle' => [
                'label' => 'Content Type',
                'type' => 'select',
                'source' => [
                    'table' => 'content_types',
                    'value_field' => 'name',
                    'label_field' => 'label'
                ],
                'help' => 'Select the content type this field should be attached to.'
            ],
            'field_name' => [
                'label' => 'Machine Name',
                'help' => 'A unique identifier for the field (e.g. "field_image"). Use lowercase and underscores only.'
            ],
            'label' => [
                'label' => 'Display Label',
                'help' => 'The name of the field as it will appear on forms (e.g. "User Biography").'
            ],
            'type' => [
                'label' => 'Field Type',
                'type' => 'select',
                'options' => [
                    'text' => 'Short Text (Single Line)',
                    'textarea' => 'Long Text (Multi-line)',
                    'integer' => 'Number (Whole Integer)',
                    'decimal' => 'Number (Decimal/Price)',
                    'boolean' => 'Boolean (Toggle/Checkbox)',
                    'date' => 'Date & Time Selector',
                    'image' => 'Image (with preview)',
                    'file' => 'File (generic upload)'
                ],
                'help' => 'The format of data this field will collect and store.'
            ],
            'required' => [
                'label' => 'Mandatory Field',
                'type' => 'checkbox',
                'options' => [
                    '1' => 'Yes, users must provide a value'
                ],
                'help' => 'Check this if the field cannot be left empty.'
            ],
            'weight' => [
                'label' => 'Ordering Weight',
                'help' => 'Lower numbers appear first in the form (e.g. -10 is above 0).'
            ]
        ];
    }
}
