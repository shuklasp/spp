<?php
namespace App\Lekhak\Entities\FieldPlugins;

use SPP\Entity\Field\FieldWidgetInterface;

/**
 * EntityReferenceWidget
 * 
 * Renders an autocomplete or select input for referencing other entities.
 */
class EntityReferenceWidget implements FieldWidgetInterface
{
    public function renderWidget(array $field, $value, array $form = []): string
    {
        $name = $field['name'] ?? 'entity_ref';
        $label = $field['label'] ?? ucfirst($name);
        $targetType = $field['widget_settings']['target_type'] ?? 'node';
        $required = !empty($field['required']) ? 'required' : '';
        $val = htmlspecialchars((string)$value, ENT_QUOTES);

        $html = "<div class='spp-form-group'>";
        $html .= "<label for='edit-{$name}'>{$label}</label>";

        // Minimal Autocomplete implementation for now
        $html .= "<input type='text' name='{$name}' id='edit-{$name}' value='{$val}' class='spp-form-element spp-autocomplete' data-target-type='{$targetType}' placeholder='Start typing to search...' {$required} />";

        if (!empty($field['help_text'])) {
            $html .= "<div class='spp-help-text'>{$field['help_text']}</div>";
        }

        $html .= "</div>";
        return $html;
    }

    public function extractValue($input)
    {
        return is_string($input) ? trim($input) : $input;
    }
}
