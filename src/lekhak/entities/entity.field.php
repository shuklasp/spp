<?php
namespace App\Lekhak\Entities;

use SPPMod\SppDb\SPPEntity;

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
            'weight' => 'int',
            'widget_type' => 'varchar(50)',
            'widget_settings' => 'text',
            'formatter_type' => 'varchar(50)',
            'formatter_settings' => 'text'
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
            ],
            'widget_type' => [
                'label' => 'Widget Type',
                'type' => 'select',
                'options' => [
                    'text_textfield' => 'Text Field',
                    'text_textarea' => 'Text Area',
                    'number' => 'Number Input',
                    'checkbox' => 'Checkbox',
                    'select' => 'Select List',
                    'radios' => 'Radio Buttons',
                    'image_image' => 'Image Upload',
                    'file_generic' => 'File Upload',
                    'date_select' => 'Date Selector'
                ],
                'help' => 'Choose the UI element used to input data for this field.'
            ],
            'widget_settings' => [
                'label' => 'Widget Settings',
                'type' => 'textarea',
                'help' => 'Additional JSON configuration for the widget (e.g. {"size": 60, "placeholder": "Enter value"}). Leave blank for defaults.'
            ],
            'formatter_type' => [
                'label' => 'Formatter Type',
                'type' => 'select',
                'options' => [
                    'text_default' => 'Default Text',
                    'text_trimmed' => 'Trimmed Text',
                    'number_integer' => 'Integer',
                    'number_decimal' => 'Decimal',
                    'boolean' => 'Boolean',
                    'image' => 'Image',
                    'file_default' => 'File Link',
                    'date_default' => 'Default Date'
                ],
                'help' => 'Choose how the data from this field should be rendered when displayed to end users.'
            ],
            'formatter_settings' => [
                'label' => 'Formatter Settings',
                'type' => 'textarea',
                'help' => 'Additional JSON configuration for the formatter (e.g. {"image_style": "thumbnail", "link": true}). Leave blank for defaults.'
            ]
        ];
    }
}
