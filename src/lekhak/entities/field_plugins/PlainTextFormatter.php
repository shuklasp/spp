<?php
namespace App\Lekhak\Entities\FieldPlugins;

use SPP\Entity\Field\FieldFormatterInterface;

/**
 * PlainTextFormatter
 * 
 * Formats a field value as safe plain text.
 */
class PlainTextFormatter implements FieldFormatterInterface
{
    public function renderDisplay(array $field, $value, string $viewMode = 'full'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $val = htmlspecialchars((string)$value, ENT_QUOTES);
        
        // Convert newlines to br if specified in settings
        if (!empty($field['formatter_settings']['nl2br'])) {
            $val = nl2br($val);
        }

        return "<div class='spp-field spp-field-{$field['name']}'>{$val}</div>";
    }
}
