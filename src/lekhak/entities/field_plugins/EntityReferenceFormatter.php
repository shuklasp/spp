<?php
namespace App\Lekhak\Entities\FieldPlugins;

use SPP\Entity\Field\FieldFormatterInterface;

/**
 * EntityReferenceFormatter
 * 
 * Formats an entity reference field as a link to the target entity.
 */
class EntityReferenceFormatter implements FieldFormatterInterface
{
    public function renderDisplay(array $field, $value, string $viewMode = 'full'): string
    {
        if (empty($value)) return '';

        // Ideally, we load the entity and get its title and URL.
        // For now, we will just output a generic link based on ID.
        $targetType = $field['formatter_settings']['target_type'] ?? 'node';
        
        // Simulating entity load
        $label = "{$targetType} #{$value}";
        $url = "/{$targetType}/{$value}";

        $html = "<div class='spp-field spp-field-{$field['name']} spp-entity-reference'>";
        if (!empty($field['formatter_settings']['link_to_entity'])) {
            $html .= "<a href='{$url}'>{$label}</a>";
        } else {
            $html .= "<span>{$label}</span>";
        }
        $html .= "</div>";

        return $html;
    }
}
