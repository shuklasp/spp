<?php
namespace App\Lekhak\Entities\FieldPlugins;

use SPP\Entity\Field\FieldWidgetInterface;

/**
 * TextWidget
 * 
 * Renders a simple text input or textarea widget.
 */
class TextWidget implements FieldWidgetInterface
{
    public function renderWidget(array $field, $value, array $form = []): string
    {
        $name = $field['name'] ?? 'field';
        $label = $field['label'] ?? ucfirst($name);
        $type = $field['widget_settings']['type'] ?? 'text'; // 'text' or 'textarea'
        $required = !empty($field['required']) ? 'required' : '';
        $val = htmlspecialchars((string)$value, ENT_QUOTES);

        $html = "<div class='spp-form-group'>";
        $html .= "<label for='edit-{$name}'>{$label}</label>";

        if ($type === 'textarea') {
            $html .= "<textarea name='{$name}' id='edit-{$name}' class='spp-form-element spp-textarea' {$required}>{$val}</textarea>";
        } else {
            $html .= "<input type='text' name='{$name}' id='edit-{$name}' value='{$val}' class='spp-form-element spp-input' {$required} />";
        }

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
