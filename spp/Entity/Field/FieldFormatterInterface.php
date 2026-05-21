<?php
namespace SPP\Entity\Field;

/**
 * Interface FieldFormatterInterface
 * 
 * Defines the contract for formatting a field's value for display.
 */
interface FieldFormatterInterface
{
    /**
     * Renders the formatted HTML for a field's display.
     *
     * @param array  $field    The field definition array.
     * @param mixed  $value    The raw value from storage.
     * @param string $viewMode The view mode (e.g., 'full', 'teaser', 'list').
     * @return string          The formatted HTML output.
     */
    public function renderDisplay(array $field, $value, string $viewMode = 'full'): string;
}
