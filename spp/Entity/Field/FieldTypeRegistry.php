<?php
namespace SPP\Entity\Field;

/**
 * Class FieldTypeRegistry
 * 
 * Central registry mapping field types (e.g., 'text', 'date', 'entity_reference')
 * to their default widgets and formatters.
 */
class FieldTypeRegistry
{
    /** @var array<string, array> */
    private static array $types = [];

    /** @var array<string, string> Map of widget alias -> class name */
    private static array $widgets = [];

    /** @var array<string, string> Map of formatter alias -> class name */
    private static array $formatters = [];

    /**
     * Register a field type.
     *
     * @param string $type            The field type identifier (e.g., 'text')
     * @param string $defaultWidget   The alias of the default widget.
     * @param string $defaultFormatter The alias of the default formatter.
     */
    public static function registerType(string $type, string $defaultWidget, string $defaultFormatter): void
    {
        self::$types[$type] = [
            'default_widget' => $defaultWidget,
            'default_formatter' => $defaultFormatter,
        ];
    }

    /**
     * Register a widget class.
     */
    public static function registerWidget(string $alias, string $className): void
    {
        if (is_subclass_of($className, FieldWidgetInterface::class)) {
            self::$widgets[$alias] = $className;
        }
    }

    /**
     * Register a formatter class.
     */
    public static function registerFormatter(string $alias, string $className): void
    {
        if (is_subclass_of($className, FieldFormatterInterface::class)) {
            self::$formatters[$alias] = $className;
        }
    }

    /**
     * Instantiate a widget for a field definition.
     *
     * @param array $field
     * @return FieldWidgetInterface|null
     */
    public static function getWidget(array $field): ?FieldWidgetInterface
    {
        $type = $field['type'] ?? 'text';
        $widgetAlias = $field['widget_type'] ?? (self::$types[$type]['default_widget'] ?? 'text_default');
        
        $class = self::$widgets[$widgetAlias] ?? null;
        if ($class && class_exists($class)) {
            return new $class();
        }
        return null;
    }

    /**
     * Instantiate a formatter for a field definition.
     *
     * @param array $field
     * @return FieldFormatterInterface|null
     */
    public static function getFormatter(array $field): ?FieldFormatterInterface
    {
        $type = $field['type'] ?? 'text';
        $formatterAlias = $field['formatter_type'] ?? (self::$types[$type]['default_formatter'] ?? 'text_plain');
        
        $class = self::$formatters[$formatterAlias] ?? null;
        if ($class && class_exists($class)) {
            return new $class();
        }
        return null;
    }
}
