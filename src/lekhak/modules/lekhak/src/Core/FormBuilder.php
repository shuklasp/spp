<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SPPView\ViewFormBuilder;
use SPPMod\SPPView\ViewForm;

/**
 * Class FormBuilder
 * Implements a Drupal Form API (FAPI) bridge.
 * Parses YAML form definitions, builds associative form arrays, dispatches hook_form_alter events,
 * and compiles the result back into ViewForm instances.
 */
class FormBuilder
{
    private static array $alterHooks = [];

    /**
     * Register a form alter callback.
     */
    public static function registerAlterHook(callable $callback)
    {
        self::$alterHooks[] = $callback;
    }

    /**
     * Builds and dispatches form alterations.
     * Yields a fully-functional ViewForm.
     */
    public static function buildForm(string $yamlPath, array $formState = []): ViewForm
    {
        // 1. Load config using existing SPP view form builder
        $config = ViewFormBuilder::loadConfig($yamlPath);
        $formId = $config['form']['name'] ?? basename($yamlPath, '.yml');

        // 2. Build Drupal-style Form API array
        $formArray = [];
        $formArray['#form_id'] = $formId;
        $formArray['#method'] = $config['form']['method'] ?? 'post';
        $formArray['#action'] = $config['form']['action'] ?? '';

        $elements = $config['elements'] ?? $config['fields'] ?? [];
        foreach ($elements as $name => $el) {
            $formArray[$name] = [
                '#type' => $el['type'] ?? 'textfield',
                '#title' => $el['label'] ?? ucfirst($name),
                '#required' => $el['required'] ?? false,
                '#default_value' => $el['value'] ?? '',
                '#description' => $el['help'] ?? ''
            ];
            // Preserve extra properties
            foreach ($el as $key => $val) {
                if (!in_array($key, ['type', 'label', 'required', 'value', 'help'])) {
                    $formArray[$name]['#' . $key] = $val;
                }
            }
        }

        // 3. Dispatch hook_form_alter and hook_form_FORM_ID_alter
        foreach (self::$alterHooks as $hook) {
            $hook($formArray, $formState, $formId);
        }

        // 4. Compile Drupal FAPI array back into SPP Config Array format
        $compiledConfig = [
            'form' => [
                'name' => $formArray['#form_id'],
                'method' => $formArray['#method'],
                'action' => $formArray['#action']
            ],
            'elements' => []
        ];

        foreach ($formArray as $name => $val) {
            if (str_starts_with($name, '#')) continue; // Skip metadata
            
            $compiledConfig['elements'][$name] = [
                'name' => $name,
                'type' => $val['#type'] === 'textfield' ? 'text' : $val['#type'],
                'label' => $val['#title'] ?? '',
                'required' => $val['#required'] ?? false,
                'value' => $val['#default_value'] ?? '',
                'help' => $val['#description'] ?? ''
            ];

            // Re-apply extra properties
            foreach ($val as $k => $v) {
                if (str_starts_with($k, '#') && !in_array($k, ['#type', '#title', '#required', '#default_value', '#description'])) {
                    $cleanKey = substr($k, 1);
                    $compiledConfig['elements'][$name][$cleanKey] = $v;
                }
            }
        }

        // 5. Generate final ViewForm instance
        return ViewFormBuilder::fromArray($compiledConfig, $formId);
    }
}
