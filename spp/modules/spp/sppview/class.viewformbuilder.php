<?php

namespace SPPMod\SPPView;

use Symfony\Component\Yaml\Yaml;

/**
 * class ViewFormBuilder
 *
 * Static factory to build ViewForm instances from YAML definitions.
 * Supports SPA service integration and validation mapping.
 *
 * @author Satya Prakash Shukla
 */
class ViewFormBuilder extends \SPP\SPPObject
{
    public static function fromYaml(string $yamlPath): ViewForm
    {
        $config = self::loadConfig($yamlPath);
        return self::fromArray($config, basename($yamlPath, '.yml'));
    }

    /**
     * Build a ViewForm from a YAML string.
     *
     * @param string $yamlContent
     * @return ViewForm
     */
    public static function fromString(string $yamlContent): ViewForm
    {
        $config = Yaml::parse($yamlContent) ?? [];
        return self::fromArray($config, 'live_preview');
    }

    /**
     * Build a ViewForm from a config array.
     */
    public static function fromArray(array $config, string $defaultName): ViewForm
    {
        $fConfig = $config['form'] ?? [];

        $form = new ViewForm(
            $fConfig['name'] ?? $defaultName,
            $fConfig['method'] ?? 'post',
            $fConfig['action'] ?? '',
            $fConfig['id'] ?? null
        );

        // Metadata assignment
        if (isset($config['entity'])) $form->setEntityClass($config['entity']);
        if (isset($config['title']))  $form->setMatter($config['title']);

        // SPA Integration
        if (!empty($fConfig['service'])) {
            $form->setAttribute('data-service', $fConfig['service']);
        }

        // Standardized Lifecycle Hooks
        if (isset($fConfig['onBeforeSubmit'])) $form->setAttribute('data-onbeforesubmit', $fConfig['onBeforeSubmit']);
        if (isset($fConfig['onSuccess']))      $form->setAttribute('data-onsuccess', $fConfig['onSuccess']);
        if (isset($fConfig['onError']))        $form->setAttribute('data-onerror', $fConfig['onError']);
        if (isset($fConfig['onRedirect']))     $form->setAttribute('data-on-redirect', $fConfig['onRedirect']);

        if (!empty($fConfig['offline'])) {
            $form->setAttribute('data-offline', 'true');
            ViewPage::addJsIncludeFile('res/js/sppoffline.js');
        }

        if (!empty($fConfig['telemetry'])) {
            $form->setAttribute('data-intelligence', 'true');
            ViewPage::addJsIncludeFile('res/js/sppintelligence.js');
        }

        // Always add auditor in dev/scaffolding context
        ViewPage::addJsIncludeFile('res/js/sppaudit.js');

        if (isset($fConfig['hotkeys'])) {
            $form->setAttribute('data-hotkeys', json_encode($fConfig['hotkeys']));
            ViewPage::addJsIncludeFile('res/js/sppintelligence.js');
        }

        if (!empty($fConfig['autosave'])) {
            $form->setAttribute('data-autosave', 'true');
            ViewPage::addJsIncludeFile('res/js/sppautosave.js');
        }

        // Handle both 'elements', 'fields', and 'settings' (module configuration format)
        $elements = $config['elements'] ?? $config['fields'] ?? $config['settings'] ?? [];

        // Handle multi-step wizards
        $steps = $config['steps'] ?? null;
        if ($steps) {
            $form->addClass('spp-wizard');
            ViewPage::addJsIncludeFile('res/js/sppwizard.js');
            
            foreach ($steps as $sIdx => $sDef) {
                $stepWrapper = new ViewTag('div', 'step_' . $sIdx);
                $stepWrapper->addClass('spp-wizard-step');
                $stepWrapper->setAttribute('data-step-title', $sDef['title'] ?? '');
                
                foreach ($sDef['fields'] ?? [] as $fName) {
                    $field = $elements[$fName] ?? null;
                    if (!$field) continue;
                    
                    $elem = self::buildElement($field);
                    if ($elem) {
                        self::populateElementMetadata($elem, $field, $form);
                        $stepWrapper->addChild($elem);
                        unset($elements[$fName]); // Remove so it's not added again below
                    }
                }
                
                // Add navigation buttons to each step
                $nav = new ViewTag('div', 'nav_' . $sIdx);
                $nav->addClass('spp-wizard-nav');
                $nav->setAttribute('style', 'margin-top: 20px; display: flex; gap: 10px;');
                
                if ($sIdx > 0) {
                    $prev = new ViewTag('button', 'prev_' . $sIdx);
                    $prev->addClass('btn-prev');
                    $prev->setMatterText('Previous');
                    $nav->addChild($prev);
                }
                
                if ($sIdx < count($steps) - 1) {
                    $next = new ViewTag('button', 'next_' . $sIdx);
                    $next->addClass('btn-next');
                    $next->setMatterText('Next');
                    $nav->addChild($next);
                }
                
                $stepWrapper->addChild($nav);
                $form->addChild($stepWrapper);
            }
        }

        foreach ($elements as $name => $field) {
            if (!is_array($field)) continue;

            // Handle associative arrays where key is the name
            if (!isset($field['name']) && is_string($name)) {
                $field['name'] = $name;
            }
            
            // Normalization for module.xml style settings (type is often 'text' instead of 'input')
            if (!isset($field['type']) && isset($field['inputtype'])) {
                $field['type'] = $field['inputtype'];
            }

            $elem = self::buildElement($field);
            if ($elem) {
                self::populateElementMetadata($elem, $field, $form);
                if ($form instanceof ViewForm) {
                    $form->addElement($elem);
                } else {
                    $form->addChild($elem);
                }
            }
        }

        return $form;
    }

    /**
     * Helper to populate metadata from field config
     */
    private static function populateElementMetadata($elem, $field, $form) {
        // Attach validations
        foreach ($field['validations'] ?? [] as $v) {
            self::attachValidationToElement($form, $elem, $v);
        }

        // Register element for binding
        $form->registerElement($elem);

        // Populate semantic metadata for modern layout
        if ($elem instanceof SPPViewForm_Element) {
            if (isset($field['label'])) $elem->setLabel((string)$field['label']);
            if (isset($field['help'])) $elem->setHelpText((string)$field['help']);
            
            foreach (['placeholder', 'value', 'readonly', 'disabled', 'rows', 'cols', 'min', 'max', 'step', 'col'] as $attr) {
                if (isset($field[$attr])) {
                    $elem->setAttribute($attr, $field[$attr]);
                }
            }
            
            if (isset($field['depends_on'])) {
                $deps = is_array($field['depends_on']) ? json_encode($field['depends_on']) : $field['depends_on'];
                $elem->setAttribute('data-depends-on', $deps);
            }

            if (isset($field['computed'])) {
                $elem->setAttribute('data-computed', $field['computed']);
                ViewPage::addJsIncludeFile('res/js/sppcomputed.js');
            }

            // Character/Word Counters
            if (isset($field['counter'])) {
                $elem->setAttribute('data-counter', $field['counter']);
                ViewPage::addJsIncludeFile('res/js/sppux.js');
            }

            // Auto-expanding textareas
            if (!empty($field['auto_expand'])) {
                $elem->addClass('spp-auto-expand');
                ViewPage::addJsIncludeFile('res/js/sppux.js');
            }

            // Password strength meter
            if (!empty($field['strength'])) {
                $elem->setAttribute('data-strength', 'true');
                ViewPage::addJsIncludeFile('res/js/spppassword.js');
            }

            // Native Voice-to-Text
            if (!empty($field['voice'])) {
                $elem->setAttribute('data-voice', 'true');
                ViewPage::addJsIncludeFile('res/js/sppintelligence.js');
            }
        }
    }

    /**
     * Build a form directly from a Module's settings definition.
     */
    public static function fromSettings(array $settings, array $values = [], string $formName = 'module_settings'): ViewForm
    {
        $config = ['settings' => $settings];
        $form = self::fromArray($config, $formName);
        
        // Bind values and add configuration marker class
        foreach ($settings as $name => $def) {
            $elem = $form->getChild($name);
            if ($elem) {
                $elem->addClass('setting-input');
                if (isset($values[$name])) {
                    $elem->setAttribute('value', $values[$name]);
                }
            }
        }
        
        return $form;
    }

    /**
     * Parses the YAML file and returns the config array.
     */
    public static function loadConfig(string $yamlPath): array
    {
        $fullPath = $yamlPath;
        if (!str_starts_with($yamlPath, '/') && !str_contains($yamlPath, ':')) {
            // Try relative to SPP_BASE_DIR first (framework-centric)
            $fullPath = SPP_BASE_DIR . '/' . ltrim($yamlPath, '/');
            if (!file_exists($fullPath)) {
                // Then try relative to SPP_APP_DIR (app-centric)
                $fullPath = SPP_APP_DIR . '/' . ltrim($yamlPath, '/');
            }
        }

        if (!file_exists($fullPath)) {
            throw new \SPP\SPPException("Form definition not found: " . $yamlPath);
        }

        return Yaml::parseFile($fullPath) ?? [];
    }

    /**
     * Map YAML field type to SPPViewForm element classes.
     */
    public static function buildElement(array $field): ?ViewTag
    {
        $name = $field['name'] ?? null;
        if (!$name) return null;

        $type = $field['type'] ?? 'input';
        $subType = $field['inputtype'] ?? 'text';

        // Resolve pre-populated value if source is defined
        $resolvedValue = $field['value'] ?? null;
        if (isset($field['default_source'])) {
            $resolvedValue = self::resolveDataSource($field['default_source']);
        }

        $elem = null;

        switch ($type) {
            case 'input':
                if ($subType === 'password') {
                    $elem = new SPPViewForm_Input_Password($name);
                } elseif ($subType === 'submit') {
                    $elem = new SPPViewForm_Input_Submit($name);
                } elseif ($subType === 'checkbox') {
                    $elem = new SPPViewForm_Input_Checkbox($name);
                } elseif ($subType === 'radio') {
                    $elem = new SPPViewForm_Input_Radio($name);
                } else {
                    $elem = new SPPViewForm_Input_Text($name);
                }
                break;

            case 'text':
                $elem = new SPPViewForm_Input_Text($name);
                break;

            case 'password':
                $elem = new SPPViewForm_Input_Password($name);
                break;

            case 'email':
                $elem = new SPPViewForm_Input_Email($name);
                break;
            
            case 'number':
                $elem = new SPPViewForm_Input_Number($name);
                break;
                
            case 'tel':
                $elem = new SPPViewForm_Input_Tel($name);
                break;
                
            case 'range':
                $elem = new SPPViewForm_Input_Range($name);
                break;
                
            case 'color':
                $elem = new SPPViewForm_Input_Color($name);
                break;

            case 'mask':
                $elem = new SPPViewForm_InputMask($name, $field['mask'] ?? '');
                break;

            case 'boolean':
            case 'toggle':
                $elem = new SPPViewForm_Toggle($name);
                break;

            case 'checkbox':
            case 'bool':
                $elem = new SPPViewForm_Input_Checkbox($name);
                break;

            case 'date':
                $elem = new SPPViewForm_DatePicker($name);
                break;

            case 'file':
                $elem = new SPPViewForm_File($name);
                if (isset($field['accept'])) $elem->setAttribute('accept', $field['accept']);
                if (!empty($field['multiple'])) $elem->setAttribute('multiple', 'multiple');
                break;

            case 'multiselect':
            case 'select':
                $optsSource = $field['source'] ?? $field['options_source'] ?? null;
                $elem = new SPPViewForm_Select($name);
                $options = [];

                if (isset($optsSource)) {
                    // Shorthand OR explicit SQL source
                    $options = self::resolveDataSource($optsSource);
                } else {
                    $options = $field['options'] ?? [];
                }

                if (is_array($options)) {
                    foreach ($options as $key => $opt) {
                        // Handle both [ {value:x, label:y} ] and { value: label } formats
                        if (is_array($opt)) {
                            $label = $opt['label'] ?? $opt['value'] ?? $opt['text'] ?? $key;
                            $val = $opt['value'] ?? $opt['id'] ?? $key;
                        } else {
                            $val = $key;
                            $label = $opt;
                        }
                        $elem->addOption($label, $val, !empty($opt['selected']));
                    }
                }
                
                if ($type === 'multiselect') {
                    $elem->setAttribute('multiple', 'multiple');
                }
                break;

            case 'textarea':
                $elem = new SPPViewForm_TextArea($name);
                break;

            case 'autocomplete':
                $elem = new SPPViewForm_Autocomplete($name, $field['source'] ?? '');
                break;
                
            case 'signature':
                $elem = new SPPViewForm_Signature($name);
                break;
            case 'tags':
                $elem = new SPPViewForm_Tags($name);
                break;
            case 'otp':
                $elem = new SPPViewForm_OTP($name, $field['digits'] ?? 6);
                break;
            case 'rating':
                $elem = new SPPViewForm_Rating($name, $field['max'] ?? 5);
                break;
            case 'range':
                $elem = new SPPViewForm_Range($name, $field['min'] ?? 0, $field['max'] ?? 100);
                break;
            case 'cropper':
                $elem = new SPPViewForm_Cropper($name);
                break;
            case 'treeselect':
                $elem = new SPPViewForm_TreeSelect($name, $field['data'] ?? []);
                break;
            case 'duallist':
                $elem = new SPPViewForm_DualList($name, $field['options'] ?? []);
                break;
            case 'portability':
                $elem = new SPPViewForm_Portability($name);
                break;

            case 'repeater':
                $elem = new SPPViewForm_Repeater($name);
                $templateFields = [];
                foreach ($field['fields'] ?? [] as $fName => $fDef) {
                    if (is_string($fName)) $fDef['name'] = $fName;
                    $child = self::buildElement($fDef);
                    if ($child) $templateFields[] = $child;
                }
                $elem->setTemplate($templateFields);
                break;

            case 'grid':
                $elem = new SPPViewForm_MasterGrid($name);
                if (isset($field['columns'])) $elem->setColumns($field['columns']);
                
                $dataSource = $field['source'] ?? $field['data_source'] ?? null;
                if ($dataSource) {
                    $elem->setData(self::resolveDataSource($dataSource));
                } elseif (isset($field['data'])) {
                    $elem->setData($field['data']);
                }
                
                if (isset($field['on_update'])) $elem->setAttribute('onUpdate', $field['on_update']);
                break;

            case 'editor':
                $elem = new SPPViewForm_Editor($name);
                break;

            case 'chart':
                $elem = new SPPViewForm_Chart($name);
                if (isset($field['chart_type'])) $elem->setChartType($field['chart_type']);
                if (isset($field['data']))       $elem->setChartData($field['data']);
                if (isset($field['options']))    $elem->setAttribute('options', $field['options']);
                break;

            case 'code':
                $elem = new SPPViewForm_CodeEditor($name);
                if (isset($field['language'])) $elem->setAttribute('language', $field['language']);
                break;

            case 'map':
                $elem = new SPPViewForm_Map($name);
                if (isset($field['center'])) $elem->setAttribute('center', $field['center']);
                if (isset($field['zoom']))   $elem->setAttribute('zoom', $field['zoom']);
                if (isset($field['markers'])) $elem->setAttribute('markers', $field['markers']);
                break;

            case 'calendar':
                $elem = new SPPViewForm_AdvancedCalendar($name);
                if (isset($field['mode'])) $elem->setAttribute('mode', $field['mode']);
                if (isset($field['enable_time'])) $elem->setAttribute('enableTime', $field['enable_time']);
                break;

            case 'sortable':
                $elem = new SPPViewForm_Sortable($name);
                if (isset($field['items'])) $elem->setAttribute('items', $field['items']);
                break;
        }

        if ($elem) {
            if (isset($field['label']))       $elem->setAttribute('label', $field['label']);
            if (isset($field['placeholder'])) $elem->setAttribute('placeholder', $field['placeholder']);
            if (isset($field['help']))        $elem->setAttribute('help', $field['help']);
            if ($resolvedValue !== null)      $elem->setAttribute('value', $resolvedValue);
            if (isset($field['class']))       $elem->addClass($field['class']);
            
            // Add metadata for client-side interactivity (depends_on)
            if (!empty($field['depends_on'])) {
                $depends = $field['depends_on'];
                $elem->setAttribute('data-depends-on', is_array($depends) ? json_encode($depends) : $depends);
            }
        }

        return $elem;
    }

    /**
     * Resolves a data source (SQL, Callback, or Expression) to a value or array of options.
     */
    public static function resolveDataSource(array $src)
    {
        $type = $src['type'] ?? 'static';
        if (isset($src['table']) || isset($src['tablename'])) {
            $type = 'sql';
        }
        
        // Resolve parameters if they use the expr: prefix
        $params = $src['params'] ?? [];
        foreach ($params as $k => $v) {
            if (is_string($v) && str_starts_with($v, 'expr:')) {
                $params[$k] = self::evaluateExpression(substr($v, 5));
            }
        }

        switch ($type) {
            case 'sql':
                $db = new \SPPMod\SPPDB\SPPDB();
                $query = $src['query'] ?? null;
                $table = $src['table'] ?? $src['tablename'] ?? null;

                if (!$query && $table) {
                    // Shorthand logic
                    if (str_starts_with(strtoupper(ltrim($table)), 'SELECT')) {
                        // Table field contains a complete query
                        $query = $table;
                    } else {
                        $valFld = $src['value_field'] ?? $src['id'] ?? $src['value'] ?? 'id';
                        $lblFld = $src['label_field'] ?? $src['name'] ?? $src['text'] ?? 'name';
                        $condition = $src['condition'] ?? $src['conditions'] ?? $src['where'] ?? '';

                        $query = "SELECT {$valFld} as value, {$lblFld} as label FROM " . \SPPMod\SPPDB\SPPDB::sppTable($table);
                        
                        if (!empty($condition)) {
                            if (is_array($condition)) {
                                $condition = implode(' AND ', $condition);
                            }
                            $query .= " WHERE " . $condition;
                        }
                    }
                }

                if (!$query) return null;

                // Execute the query
                $result = $db->execute_query($query, $params);
                
                // Format results as standard label/value pairs for the Select element
                $formatted = [];
                foreach ($result as $row) {
                    if (isset($row['value']) && isset($row['label'])) {
                        $formatted[] = ['value' => $row['value'], 'label' => $row['label']];
                    } else {
                        // Fallback: use first and second columns as value and label
                        $vals = array_values($row);
                        $formatted[] = [
                            'value' => $vals[0] ?? null,
                            'label' => $vals[1] ?? ($vals[0] ?? null)
                        ];
                    }
                }
                return $formatted;

            case 'callback':
                $method = $src['method'] ?? null;
                if ($method && is_callable($method)) {
                    return call_user_func_array($method, $params);
                }
                break;

            case 'static':
                return $src['value'] ?? null;
        }

        return null;
    }

    /**
     * Safely evaluates a class::method() expression.
     */
    private static function evaluateExpression(string $expr)
    {
        if (str_contains($expr, '::')) {
            if (is_callable($expr)) {
                return call_user_func($expr);
            }
        }
        return $expr;
    }

    /**
     * Maps YAML validation config to SPP validator instances and attaches them.
     */
    private static function attachValidationToElement(ViewForm $form, ViewTag $elem, array $vConfig): void
    {
        $type = $vConfig['type'] ?? null;
        if (!$type) return;

        $msg = $vConfig['message'] ?? 'Validation failed';
        $errHolder = $vConfig['errorholder'] ?? ($elem->getAttribute('id') . '_error');
        $event = $vConfig['event'] ?? 'onblur';

        $validator = null;
        switch ($type) {
            case 'required':
                $validator = new SPP_Validator_RequiredValidator($elem, $errHolder, $msg);
                break;
            case 'numeric':
                $validator = new SPP_Validator_NumericValidator($elem, $errHolder, $msg);
                break;
            case 'email':
                $validator = new SPP_Validator_EmailValidator($elem, $errHolder, $msg);
                break;
            case 'min':
                $min = $vConfig['value'] ?? $vConfig['min'] ?? 0;
                $validator = new SPP_Validator_MinLengthValidator($elem, $min, $errHolder, $msg);
                $validator->minlength = $min;
                break;
            case 'max':
                $max = $vConfig['value'] ?? $vConfig['max'] ?? 100;
                $validator = new SPP_Validator_MaxLengthValidator($elem, $max, $errHolder, $msg);
                $validator->maxlength = $max;
                break;
            case 'url':
                $validator = new SPP_Validator_UrlValidator($elem, $errHolder, $msg);
                break;
            case 'json':
                $validator = new SPP_Validator_JsonValidator($elem, $errHolder, $msg);
                break;
            case 'creditcard':
                $validator = new SPP_Validator_CreditCardValidator($elem, $errHolder, $msg);
                break;
            case 'range':
                $min = $vConfig['min'] ?? 0;
                $max = $vConfig['max'] ?? 100;
                $validator = new SPP_Validator_RangeValidator($elem, $min, $max, $errHolder, $msg);
                break;
            case 'match':
                $target = $vConfig['target'] ?? '';
                $validator = new SPP_Validator_MatchValidator($elem, $target, $errHolder, $msg);
                break;
            case 'in':
                $options = $vConfig['options'] ?? [];
                $validator = new SPP_Validator_InArrayValidator($elem, $options, $errHolder, $msg);
                break;
            case 'unique':
                $table = $vConfig['table'] ?? '';
                $column = $vConfig['column'] ?? $elem->getAttribute('name');
                $validator = new SPP_Validator_UniqueValidator($elem, $table, $column, $errHolder, $msg);
                break;
            case 'filesize':
                $max = $vConfig['max'] ?? 2097152;
                $validator = new SPP_Validator_FileSizeValidator($elem, $max, $errHolder, $msg);
                break;
            case 'extension':
                $allowed = $vConfig['allowed'] ?? [];
                $validator = new SPP_Validator_FileExtensionValidator($elem, $allowed, $errHolder, $msg);
                break;
            case 'aadhaar':
                $validator = new SPP_Validator_AadhaarValidator($elem, $errHolder, $msg);
                break;
            case 'pan':
                $validator = new SPP_Validator_PanValidator($elem, $errHolder, $msg);
                break;
            case 'gstin':
                $validator = new SPP_Validator_GstinValidator($elem, $errHolder, $msg);
                break;
            case 'ifsc':
                $validator = new SPP_Validator_IfscValidator($elem, $errHolder, $msg);
                break;
            case 'pincode':
                $validator = new SPP_Validator_PincodeValidator($elem, $errHolder, $msg);
                break;
            case 'indmobile':
                $validator = new SPP_Validator_IndiaMobileValidator($elem, $errHolder, $msg);
                break;
            case 'dateafter':
                $target = $vConfig['target'] ?? '';
                $validator = new SPP_Validator_DateAfterValidator($elem, $target, $errHolder, $msg);
                break;
            case 'passwordstrength':
                $min = $vConfig['min'] ?? 3;
                $validator = new SPP_Validator_PasswordStrengthValidator($elem, $min, $errHolder, $msg);
                break;
            case 'iban':
                $validator = new SPP_Validator_IbanValidator($elem, $errHolder, $msg);
                break;
            case 'requiredif':
                $target = $vConfig['target'] ?? '';
                $val = $vConfig['value'] ?? '';
                $validator = new SPP_Validator_RequiredIfValidator($elem, $target, $val, $errHolder, $msg);
                break;
            case 'gt':
                $target = $vConfig['target'] ?? '';
                $validator = new SPP_Validator_GreaterThanValidator($elem, $target, $errHolder, $msg);
                break;
            case 'ip':
                $validator = new SPP_Validator_IpValidator($elem, $errHolder, $msg);
                break;
            case 'mac':
                $validator = new SPP_Validator_MacAddressValidator($elem, $errHolder, $msg);
                break;
            case 'isbn':
                $validator = new SPP_Validator_IsbnValidator($elem, $errHolder, $msg);
                break;
            case 'remote':
                $url = $vConfig['url'] ?? '';
                $validator = new SPP_Validator_RemoteValidator($elem, $url, $errHolder, $msg);
                break;
        }

        if ($validator) {
            // Update JS function if it has parameters
            if ($type === 'min') {
                $validator->setJsParams([$validator->minlength]);
            } elseif ($type === 'max') {
                $validator->setJsParams([$validator->maxlength]);
            } elseif ($type === 'range') {
                $validator->setJsParams([$validator->min, $validator->max]);
            } elseif ($type === 'match') {
                $validator->setJsParams([$validator->targetField]);
            } elseif ($type === 'in') {
                $validator->setJsParams([json_encode($validator->options)]);
            } elseif ($type === 'dateafter') {
                $validator->setJsParams([$validator->targetField]);
            } elseif ($type === 'passwordstrength') {
                $validator->setJsParams([$validator->minScore]);
            } elseif ($type === 'requiredif') {
                $validator->setJsParams([$validator->targetField, $validator->targetValue]);
            } elseif ($type === 'gt') {
                $validator->setJsParams([$validator->targetField]);
            } elseif ($type === 'remote') {
                $validator->setJsParams([$validator->url]);
            }
            
            $form->addValidator($validator);
            $form->attachValidator($validator, $elem, $event, $errHolder, $msg);
        }
    }

    /**
     * Static utility to validate raw data against a YAML form definition.
     * Returns ['valid' => bool, 'errors' => [field => message]]
     */
    public static function validate(string $yamlPath, array $data): array
    {
        $fullPath = (str_starts_with($yamlPath, '/') || str_contains($yamlPath, ':')) 
            ? $yamlPath 
            : SPP_APP_DIR . '/' . ltrim($yamlPath, '/');

        $config = Yaml::parseFile($fullPath);
        $errors = [];
        $isValid = true;

        foreach ($config['fields'] ?? [] as $field) {
            $name = $field['name'];
            $val = $data[$name] ?? null;

            foreach ($field['validations'] ?? [] as $vCfg) {
                // We use a temporary element to satisfy validator constructor
                $tempElem = new ViewTag('input', $name);
                $validator = null;
                
                switch ($vCfg['type']) {
                    case 'required':
                        $validator = new SPP_Validator_RequiredValidator($tempElem);
                        break;
                    case 'numeric':
                        $validator = new SPP_Validator_NumericValidator($tempElem);
                        break;
                }

                if ($validator && !$validator->validate($val)) {
                    $errors[$name] = $vCfg['message'] ?? 'Invalid value';
                    $isValid = false;
                    break; // stop on first error for this field
                }
            }
        }

        return ['valid' => $isValid, 'errors' => $errors];
    }
}
