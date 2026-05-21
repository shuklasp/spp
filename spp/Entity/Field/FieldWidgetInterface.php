<?php
namespace SPP\Entity\Field;

/**
 * Interface FieldWidgetInterface
 * 
 * Defines the contract for rendering an input form element for a field
 * and extracting the submitted value.
 */
interface FieldWidgetInterface
{
    /**
     * Renders the HTML for the input widget.
     *
     * @param array $field  The field definition array.
     * @param mixed $value  The current value of the field.
     * @param array $form   The current form context/state.
     * @return string       The rendered HTML widget.
     */
    public function renderWidget(array $field, $value, array $form = []): string;

    /**
     * Extracts and sanitizes the value from user input.
     *
     * @param mixed $input  The raw input value submitted for this field.
     * @return mixed        The sanitized/transformed value ready for storage.
     */
    public function extractValue($input);
}
