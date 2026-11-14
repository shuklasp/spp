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
    private $entity;

    public function __construct($entity = null)
    {
        $this->entity = $entity;
    }

    public static function fromYaml(string $yamlPath): ViewForm
    {
        $config = self::loadConfig($yamlPath);
        return self::fromArray($config, basename($yamlPath, '.yml'));
    }

    /**
     * Build a form for the current entity.
     */
    public function build(): ViewForm
    {
        if (!$this->entity) {
            throw new \SPP\SPPException("No entity provided for form builder.");
        }

        $config = [
            'form' => [
                'name' => strtolower((new \ReflectionClass($this->entity))->getShortName()) . '_form',
                'method' => 'post'
            ],
            'elements' => []
        ];

        // Auto-generate fields from entity attributes
        if (method_exists($this->entity, 'define_attributes')) {
            $attrs = $this->entity->define_attributes();
            $metadata = method_exists($this->entity, 'field_metadata') ? $this->entity->field_metadata() : [];

            foreach ($attrs as $name => $type) {
                if ($name === 'id' || $name === 'created' || $name === 'updated') {
                    continue;
                }

                $fieldMeta = $metadata[$name] ?? [];
                $config['elements'][$name] = array_merge([
                    'name' => $name,
                    'label' => $fieldMeta['label'] ?? ucfirst(str_replace('_', ' ', $name)),
                    'type' => $fieldMeta['type'] ?? (str_contains($type, 'text') ? 'textarea' : 'input'),
                    'help' => $fieldMeta['help'] ?? null
                ], $fieldMeta);
            }
        }

        $form = self::fromArray($config, $config['form']['name']);
        if ($this->entity instanceof \SPPMod\SPPDB\SPPEntity) {
            $form->setEntityInstance($this->entity);
            $form->setEntityClass(get_class($this->entity));
            $form->bind($this->entity);
        }

        return $form;
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
        if (!is_array($config)) {
            $config = [];
        }
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

        // Auto-discover app/global validators
        \SPPMod\SPPView\Validation\CustomRuleRegistry::autoDiscover();

        // Load form-specific local validators if defined in YAML
        if (isset($config['validators']) && is_array($config['validators'])) {
            \SPPMod\SPPView\Validation\CustomRuleRegistry::loadFromArray(['validators' => $config['validators']]);
        }

        // Metadata assignment
        if (isset($config['entity'])) {
            $form->setEntityClass($config['entity']);
        }
        if (isset($config['title'])) {
            $form->setMatter($config['title']);
        }

        // SPA Integration
        if (!empty($fConfig['service'])) {
            $form->setAttribute('data-service', $fConfig['service']);
        }

        // Standardized Lifecycle Hooks
        if (isset($fConfig['onBeforeSubmit'])) {
            $form->setAttribute('data-onbeforesubmit', $fConfig['onBeforeSubmit']);
        }
        if (isset($fConfig['onSuccess'])) {
            $form->setAttribute('data-onsuccess', $fConfig['onSuccess']);
        }
        if (isset($fConfig['onError'])) {
            $form->setAttribute('data-onerror', $fConfig['onError']);
        }
        if (isset($fConfig['onRedirect'])) {
            $form->setAttribute('data-on-redirect', $fConfig['onRedirect']);
        }

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
                    if (!$field) {
                        continue;
                    }

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
            if (!is_array($field)) {
                continue;
            }

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

        // Top-level honeypot defense (if not already added in elements)
        if (!empty($config['honeypot'])) {
            $hpName = is_string($config['honeypot']) ? $config['honeypot'] : 'website_hp_field';
            if (!$form->getChild($hpName)) {
                $hpElem = new SPPViewForm_Honeypot($hpName);
                $form->addElement($hpElem);
                $form->registerElement($hpElem);
            }
        }

        // Top-level workflow actions bar (if not already added in elements)
        if (!empty($config['workflow_actions']) || !empty($config['workflow'])) {
            if (!$form->getChild('_workflow_actions')) {
                $wfDef = is_array($config['workflow_actions'] ?? null) ? $config['workflow_actions'] : (is_array($config['workflow'] ?? null) ? $config['workflow'] : []);
                $transitions = $wfDef['transitions'] ?? ($config['workflow_actions'] === true ? ['submit' => 'Submit', 'approve' => 'Approve'] : []);
                $currentState = $wfDef['current_state'] ?? ($config['workflow_actions'] === true ? 'draft' : '');
                $wfActions = new SPPViewForm_WorkflowActions(
                    '_workflow_actions',
                    $transitions,
                    $currentState,
                    $wfDef['show_comment'] ?? false
                );
                $form->addElement($wfActions);
                $form->registerElement($wfActions);
            }
        }

        // Auto-attach Honeypot validator if any honeypot element exists in form
        foreach ($form->get('element') ?: [] as $elName => $elObj) {
            if ($elObj instanceof SPPViewForm_Honeypot) {
                if (class_exists('\\SPPMod\\SPPView\\SPPHoneypotValidator')) {
                    $form->addValidator(new \SPPMod\SPPView\SPPHoneypotValidator($elObj, 'honeypoterror', 'Spam submission detected.'));
                }
            }
        }

        // Form-level validators
        foreach ($config['validators'] ?? [] as $vDef) {
            if (is_array($vDef)) {
                $vType = strtolower($vDef['type'] ?? '');
                $vMsg = $vDef['message'] ?? 'Validation failed.';
                if ($vType === 'workflow_guard' || $vType === 'guard') {
                    $entityClass = $vDef['entity'] ?? ($config['entity'] ?? '');
                    $state = $vDef['state'] ?? '';
                    if (class_exists('\\SPPMod\\SPPView\\SPPWorkflowGuardValidator')) {
                        $form->addValidator(new \SPPMod\SPPView\SPPWorkflowGuardValidator(null, $entityClass, $state, 'workflow_error', $vMsg));
                    }
                } elseif ($vType === 'honeypot') {
                    $hpField = $vDef['field'] ?? 'website_hp_field';
                    $hpElem = $form->getChild($hpField);
                    if ($hpElem && class_exists('\\SPPMod\\SPPView\\SPPHoneypotValidator')) {
                        $form->addValidator(new \SPPMod\SPPView\SPPHoneypotValidator($hpElem, 'honeypoterror', $vMsg));
                    }
                }
            }
        }

        return $form;
    }

    /**
     * Helper to populate metadata and validations from field config
     */
    private static function populateElementMetadata($elem, $field, $form)
    {
        // Normalize all validations (rules string, array of rules, or inline attributes)
        $validations = self::normalizeFieldValidations($field);
        foreach ($validations as $v) {
            self::attachValidationToElement($form, $elem, $v);
        }

        // Register element for binding
        $form->registerElement($elem);

        // Populate semantic metadata for modern layout
        if ($elem instanceof SPPViewForm_Element) {
            if (isset($field['label'])) {
                $elem->setLabel((string) $field['label']);
            }
            if (isset($field['help'])) {
                $elem->setHelpText((string) $field['help']);
            }

            foreach (['placeholder', 'value', 'readonly', 'disabled', 'rows', 'cols', 'min', 'max', 'step', 'style'] as $attr) {
                if (isset($field[$attr])) {
                    $elem->setAttribute($attr, $field[$attr]);
                }
            }

            if (isset($field['class'])) {
                $elem->addClass($field['class']);
            }

            if (isset($field['col'])) {
                $col = (int)$field['col'];
                $elem->setAttribute('data-col', (string)$col);
                $elem->addClass("col-md-{$col}");
            }

            if (isset($field['depends_on'])) {
                $deps = is_array($field['depends_on']) ? json_encode($field['depends_on']) : $field['depends_on'];
                $elem->setAttribute('data-depends-on', $deps);
                ViewPage::addJsIncludeFile('res/js/sppdependencies.js');
            }

            if (isset($field['computed'])) {
                $elem->setAttribute('data-computed', (string)$field['computed']);
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
     * Normalizes field validation declarations from multiple flexible YAML formats:
     * - Shorthand pipe string: "required|email|min:5|unique:users,email"
     * - Shorthand string array: ['required', 'email', 'min:5']
     * - Key-value pair array: [['min' => 5], ['max' => 100]]
     * - Full object array: [['type' => 'required', 'message' => 'Error']]
     * - Inline field attributes: required: true, min: 5, max: 100, unique: "users,email"
     */
    public static function normalizeFieldValidations(array $field): array
    {
        $validations = [];

        // 1. Process explicit rules / validations / validate keys
        $rawSources = [];
        foreach (['validations', 'rules', 'validate'] as $key) {
            if (isset($field[$key])) {
                $rawSources[] = $field[$key];
            }
        }

        foreach ($rawSources as $raw) {
            if (is_string($raw)) {
                $parts = explode('|', $raw);
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $validations[] = self::parseRuleString($p);
                    }
                }
            } elseif (is_array($raw)) {
                foreach ($raw as $item) {
                    if (is_string($item)) {
                        $validations[] = self::parseRuleString($item);
                    } elseif (is_array($item)) {
                        if (isset($item['type'])) {
                            $validations[] = $item;
                        } else {
                            foreach ($item as $rType => $rVal) {
                                $validations[] = self::parseRuleKeyValue((string)$rType, $rVal);
                            }
                        }
                    }
                }
            }
        }

        // 2. Process inline shorthand field attributes
        if (!empty($field['required'])) {
            $validations[] = ['type' => 'required', 'message' => $field['required_message'] ?? 'This field is required.'];
        }
        if (isset($field['min']) && is_numeric($field['min'])) {
            $validations[] = ['type' => 'min', 'value' => (int)$field['min']];
        }
        if (isset($field['minlength']) && is_numeric($field['minlength'])) {
            $validations[] = ['type' => 'min', 'value' => (int)$field['minlength']];
        }
        if (isset($field['max']) && is_numeric($field['max']) && ($field['type'] ?? '') !== 'rating') {
            $validations[] = ['type' => 'max', 'value' => (int)$field['max']];
        }
        if (isset($field['maxlength']) && is_numeric($field['maxlength'])) {
            $validations[] = ['type' => 'max', 'value' => (int)$field['maxlength']];
        }
        if (!empty($field['unique'])) {
            $val = $field['unique'];
            if (is_string($val) && str_contains($val, ',')) {
                [$tbl, $col] = explode(',', $val, 2);
                $validations[] = ['type' => 'unique', 'table' => trim($tbl), 'column' => trim($col)];
            } else {
                $validations[] = ['type' => 'unique', 'table' => (string)$val];
            }
        }
        if (!empty($field['match'])) {
            $validations[] = ['type' => 'match', 'target' => (string)$field['match']];
        }
        if (!empty($field['pattern'])) {
            $validations[] = ['type' => 'regex', 'pattern' => (string)$field['pattern']];
        }
        if (!empty($field['regex'])) {
            $validations[] = ['type' => 'regex', 'pattern' => (string)$field['regex']];
        }
        if (isset($field['range'])) {
            $r = $field['range'];
            if (is_array($r) && count($r) >= 2) {
                $validations[] = ['type' => 'range', 'min' => $r[0], 'max' => $r[1]];
            } elseif (is_string($r)) {
                $parts = str_contains($r, '..') ? explode('..', $r, 2) : explode(',', $r, 2);
                $validations[] = ['type' => 'range', 'min' => (int)$parts[0], 'max' => (int)($parts[1] ?? 100)];
            }
        }
        if (!empty($field['filesize'])) {
            $validations[] = ['type' => 'filesize', 'max' => (int)$field['filesize']];
        }
        if (!empty($field['extension'])) {
            $validations[] = ['type' => 'extension', 'allowed' => (array)$field['extension']];
        }
        if (!empty($field['passwordstrength'])) {
            $validations[] = ['type' => 'passwordstrength', 'min' => (int)$field['passwordstrength']];
        }

        // Specific format booleans
        foreach (['email', 'numeric', 'url', 'json', 'creditcard', 'aadhaar', 'pan', 'gstin', 'ifsc', 'pincode', 'indmobile', 'iban', 'ip', 'mac', 'isbn'] as $fmt) {
            if (!empty($field[$fmt]) && $field[$fmt] === true) {
                $validations[] = ['type' => $fmt];
            }
        }

        // Auto-detect registered custom rules declared directly on field (e.g. pan: true, or custom_rule: true / custom_rule: { params: [...] })
        foreach ($field as $fKey => $fVal) {
            $kLower = strtolower((string)$fKey);
            if (in_array($kLower, ['type', 'name', 'id', 'label', 'rules', 'validations', 'validate', 'options', 'value', 'default', 'placeholder', 'class', 'style', 'inputtype'], true)) {
                continue;
            }
            if ($fVal === true && \SPPMod\SPPView\Validation\CustomRuleRegistry::has($kLower)) {
                $validations[] = ['type' => $kLower];
            } elseif (is_array($fVal) && \SPPMod\SPPView\Validation\CustomRuleRegistry::has($kLower)) {
                $validations[] = array_merge(['type' => $kLower], $fVal);
            }
        }

        // Auto-detect type-based validations
        $fieldType = strtolower($field['type'] ?? '');
        if ($fieldType === 'email') {
            $validations[] = ['type' => 'email'];
        } elseif ($fieldType === 'number') {
            $validations[] = ['type' => 'numeric'];
        } elseif ($fieldType === 'url') {
            $validations[] = ['type' => 'url'];
        }

        // Deduplicate validations by type
        $unique = [];
        $seen = [];
        foreach ($validations as $v) {
            $t = strtolower($v['type'] ?? '');
            if (!$t) continue;
            $key = $t . (isset($v['value']) ? ':' . $v['value'] : '') . (isset($v['target']) ? ':' . $v['target'] : '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $v;
            }
        }

        return $unique;
    }

    private static function parseRuleString(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $param] = explode(':', $rule, 2);
            return self::parseRuleKeyValue(trim($name), trim($param));
        }
        return ['type' => trim($rule)];
    }

    private static function parseRuleKeyValue(string $type, $val): array
    {
        $type = strtolower($type);
        switch ($type) {
            case 'min':
            case 'minlength':
                return ['type' => 'min', 'value' => (int)$val];
            case 'max':
            case 'maxlength':
                return ['type' => 'max', 'value' => (int)$val];
            case 'unique':
                if (is_string($val) && str_contains($val, ',')) {
                    [$tbl, $col] = explode(',', $val, 2);
                    return ['type' => 'unique', 'table' => trim($tbl), 'column' => trim($col)];
                }
                return ['type' => 'unique', 'table' => (string)$val];
            case 'match':
            case 'same':
            case 'confirmed':
                return ['type' => 'match', 'target' => (string)$val];
            case 'regex':
            case 'pattern':
                return ['type' => 'regex', 'pattern' => (string)$val];
            case 'range':
            case 'between':
                if (is_string($val)) {
                    $parts = str_contains($val, '..') ? explode('..', $val, 2) : explode(',', $val, 2);
                    return ['type' => 'range', 'min' => (int)($parts[0] ?? 0), 'max' => (int)($parts[1] ?? 100)];
                } elseif (is_array($val)) {
                    return ['type' => 'range', 'min' => (int)($val[0] ?? 0), 'max' => (int)($val[1] ?? 100)];
                }
                return ['type' => 'range'];
            case 'in':
                $opts = is_array($val) ? $val : array_map('trim', explode(',', (string)$val));
                return ['type' => 'in', 'options' => $opts];
            case 'extension':
            case 'ext':
            case 'mimes':
                $allowed = is_array($val) ? $val : array_map('trim', explode(',', (string)$val));
                return ['type' => 'extension', 'allowed' => $allowed];
            case 'filesize':
            case 'maxsize':
                return ['type' => 'filesize', 'max' => (int)$val];
            case 'dateafter':
            case 'after':
                return ['type' => 'dateafter', 'target' => (string)$val];
            case 'passwordstrength':
                return ['type' => 'passwordstrength', 'min' => (int)$val];
            case 'requiredif':
                $parts = explode(',', (string)$val, 2);
                return ['type' => 'requiredif', 'target' => trim($parts[0] ?? ''), 'value' => trim($parts[1] ?? '')];
            case 'gt':
                return ['type' => 'gt', 'target' => (string)$val];
            case 'workflow_guard':
            case 'workflowguard':
            case 'guard':
                $parts = explode(',', (string)$val, 2);
                return ['type' => 'workflow_guard', 'entity' => trim($parts[0] ?? ''), 'state' => trim($parts[1] ?? '')];
            default:
                return ['type' => $type, 'value' => $val];
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
            if (class_exists('\\SPPMod\\SPPView\\Forms')) {
                $found = Forms::findFormFile($yamlPath);
                if ($found && file_exists($found)) {
                    $fullPath = $found;
                }
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
        if (!$name) {
            return null;
        }

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
                } elseif ($subType === 'checkbox' || $subType === 'radio') {
                    $cls = ($subType === 'checkbox') ? 'SPPMod\SPPView\SPPViewForm_Input_Checkbox' : 'SPPMod\SPPView\SPPViewForm_Input_Radio';
                    $elem = new $cls($name);
                    $options = $field['options'] ?? [];
                    if (isset($field['source'])) {
                        $options = self::resolveDataSource($field['source']);
                    }
                    foreach ($options as $key => $opt) {
                        if (is_array($opt)) {
                            $label = $opt['label'] ?? $opt['value'] ?? $key;
                            $val = $opt['value'] ?? $opt['id'] ?? $key;
                        } else {
                            $val = $key;
                            $label = $opt;
                        }
                        $elem->addOption($val, $label, (isset($field['value']) && $field['value'] == $val));
                    }
                } else {
                    $elem = new SPPViewForm_Input_Text($name);
                }
                break;

            case 'radio':
            case 'checkbox':
                $cls = ($type === 'checkbox') ? 'SPPMod\SPPView\SPPViewForm_Input_Checkbox' : 'SPPMod\SPPView\SPPViewForm_Input_Radio';
                $elem = new $cls($name);
                $options = $field['options'] ?? [];
                if (isset($field['source'])) {
                    $options = self::resolveDataSource($field['source']);
                }
                foreach ($options as $key => $opt) {
                    if (is_array($opt)) {
                        $label = $opt['label'] ?? $opt['value'] ?? $key;
                        $val = $opt['value'] ?? $opt['id'] ?? $key;
                    } else {
                        $val = $key;
                        $label = $opt;
                    }
                    $elem->addOption($val, $label, (isset($field['value']) && $field['value'] == $val));
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

            case 'hidden':
                $elem = new SPPViewForm_Input_Hidden($name);
                break;

            case 'submit':
                $elem = new SPPViewForm_Input_Submit($name);
                break;

            case 'button':
                $elem = new SPPViewForm_Input_Button($name);
                break;

            case 'tel':
            case 'phone':
                $elem = new SPPViewForm_Input_Tel($name);
                break;

            case 'url':
                $elem = new SPPViewForm_Input_Text($name);
                $elem->setAttribute('type', 'url');
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
                if (isset($field['accept'])) {
                    $elem->setAttribute('accept', $field['accept']);
                }
                if (!empty($field['multiple'])) {
                    $elem->setAttribute('multiple', 'multiple');
                }
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

            case 'honeypot':
            case 'antispam':
                $elem = new SPPViewForm_Honeypot($name);
                break;

            case 'workflow_actions':
            case 'workflow_bar':
            case 'workflow':
                $elem = new SPPViewForm_WorkflowActions(
                    $name,
                    $field['transitions'] ?? [],
                    $field['current_state'] ?? '',
                    $field['show_comment'] ?? false
                );
                break;

            case 'repeater':
                $elem = new SPPViewForm_Repeater($name);
                $templateFields = [];
                $rawFields = $field['fields'] ?? $field['schema'] ?? [];
                foreach ($rawFields as $fName => $fDef) {
                    if (is_string($fName) && is_array($fDef)) {
                        $fDef['name'] = $fDef['name'] ?? $fName;
                    }
                    if (is_array($fDef)) {
                        $child = self::buildElement($fDef);
                        if ($child) {
                            $templateFields[] = $child;
                        }
                    }
                }
                $elem->setTemplate($templateFields);
                break;

            case 'grid':
                $elem = new SPPViewForm_MasterGrid($name);
                if (isset($field['columns'])) {
                    $elem->setColumns($field['columns']);
                }

                $dataSource = $field['source'] ?? $field['data_source'] ?? null;
                if ($dataSource) {
                    $elem->setData(self::resolveDataSource($dataSource));
                } elseif (isset($field['data'])) {
                    $elem->setData($field['data']);
                }

                if (isset($field['on_update'])) {
                    $elem->setAttribute('onUpdate', $field['on_update']);
                }
                break;

            case 'editor':
            case 'wysiwyg':
            case 'rich_text':
                $elem = new SPPViewForm_Editor($name);
                break;

            case 'chart':
                $elem = new SPPViewForm_Chart($name);
                if (isset($field['chart_type'])) {
                    $elem->setChartType($field['chart_type']);
                }
                if (isset($field['data'])) {
                    $elem->setChartData($field['data']);
                }
                if (isset($field['options'])) {
                    $elem->setAttribute('options', $field['options']);
                }
                break;

            case 'code':
                $elem = new SPPViewForm_CodeEditor($name);
                if (isset($field['language'])) {
                    $elem->setAttribute('language', $field['language']);
                }
                break;

            case 'map':
                $elem = new SPPViewForm_Map($name);
                if (isset($field['center'])) {
                    $elem->setAttribute('center', $field['center']);
                }
                if (isset($field['zoom'])) {
                    $elem->setAttribute('zoom', $field['zoom']);
                }
                if (isset($field['markers'])) {
                    $elem->setAttribute('markers', $field['markers']);
                }
                break;

            case 'calendar':
                $elem = new SPPViewForm_AdvancedCalendar($name);
                if (isset($field['mode'])) {
                    $elem->setAttribute('mode', $field['mode']);
                }
                if (isset($field['enable_time'])) {
                    $elem->setAttribute('enableTime', $field['enable_time']);
                }
                break;

            case 'sortable':
                $elem = new SPPViewForm_Sortable($name);
                if (isset($field['items'])) {
                    $elem->setAttribute('items', $field['items']);
                }
                break;
        }

        if ($elem) {
            if (isset($field['label'])) {
                $elem->setAttribute('label', $field['label']);
            }
            if (isset($field['placeholder'])) {
                $elem->setAttribute('placeholder', $field['placeholder']);
            }
            if (isset($field['help'])) {
                $elem->setAttribute('help', $field['help']);
            }
            if ($resolvedValue !== null) {
                $elem->setAttribute('value', $resolvedValue);
            }
            if (isset($field['class'])) {
                $elem->addClass($field['class']);
            }

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
                                $conds = [];
                                foreach ($condition as $k => $v) {
                                    if (is_int($k)) {
                                        // Ignore integer keys since they can't be parameterized safely
                                        continue;
                                    } else {
                                        if (preg_match('/^[a-zA-Z0-9_]+$/', $k)) {
                                            $conds[] = "{$k} = ?";
                                            $params[] = $v;
                                        } else {
                                            throw new \SPP\SPPException("Invalid column name in condition: {$k}");
                                        }
                                    }
                                }
                                $condition = implode(' AND ', $conds);
                            } else if (is_string($condition)) {
                                // For backward compatibility, allow strings but log a warning.
                                // In strict mode, we should throw an exception.
                                error_log("SPP Warning: Raw string conditions in resolveDataSource are deprecated and potentially vulnerable to SQLi.");
                            }
                            if (!empty($condition)) {
                                $query .= " WHERE " . $condition;
                            }
                        }
                    }
                }

                if (!$query) {
                    return null;
                }

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
        if (!$type) {
            return;
        }

        $type = strtolower((string)$type);
        if ($type === 'number' || $type === 'integer' || $type === 'int') $type = 'numeric';
        if ($type === 'minlength') $type = 'min';
        if ($type === 'maxlength') $type = 'max';
        if ($type === 'between') $type = 'range';
        if ($type === 'same' || $type === 'confirmed') $type = 'match';
        if ($type === 'mobile') $type = 'indmobile';
        if ($type === 'mimes' || $type === 'ext') $type = 'extension';
        if ($type === 'maxsize') $type = 'filesize';
        if ($type === 'after') $type = 'dateafter';
        if ($type === 'password_strength') $type = 'passwordstrength';
        if ($type === 'required_if') $type = 'requiredif';
        if ($type === 'greater_than') $type = 'gt';
        if ($type === 'ipv4' || $type === 'ipv6') $type = 'ip';

        $msg = $vConfig['message'] ?? 'Validation failed';
        $errHolder = $vConfig['errorholder'] ?? ($elem->getAttribute('id') . '_error');
        $event = $vConfig['event'] ?? 'onblur';

        $validator = null;
        switch ($type) {
            case 'required':
                $validator = new SPP_Validator_RequiredValidator($elem, $errHolder, $msg);
                $elem->setAttribute('required', 'required');
                break;
            case 'numeric':
                $validator = new SPP_Validator_NumericValidator($elem, $errHolder, $msg);
                $elem->setAttribute('type', 'number');
                break;
            case 'email':
                $validator = new SPP_Validator_EmailValidator($elem, $errHolder, $msg);
                $elem->setAttribute('type', 'email');
                break;
            case 'min':
                $min = $vConfig['value'] ?? $vConfig['min'] ?? 0;
                $validator = new SPP_Validator_MinLengthValidator($elem, $min, $errHolder, $msg);
                $validator->minlength = $min;
                $elem->setAttribute('minlength', $min);
                break;
            case 'max':
                $max = $vConfig['value'] ?? $vConfig['max'] ?? 100;
                $validator = new SPP_Validator_MaxLengthValidator($elem, $max, $errHolder, $msg);
                $validator->maxlength = $max;
                $elem->setAttribute('maxlength', $max);
                break;
            case 'url':
                $validator = new SPP_Validator_UrlValidator($elem, $errHolder, $msg);
                $elem->setAttribute('type', 'url');
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
                $elem->setAttribute('min', $min);
                $elem->setAttribute('max', $max);
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
            case 'regex':
            case 'pattern':
                $pattern = $vConfig['pattern'] ?? $vConfig['regex'] ?? '';
                $validator = new SPP_Validator_RegexValidator($elem, $pattern, $errHolder, $msg);
                if ($pattern)
                    $elem->setAttribute('pattern', ltrim(rtrim($pattern, '/'), '/'));
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
                $elem->setAttribute('pattern', '\d{12}');
                break;
            case 'pan':
                $validator = new SPP_Validator_PanValidator($elem, $errHolder, $msg);
                $elem->setAttribute('pattern', '[A-Z]{5}[0-9]{4}[A-Z]{1}');
                break;
            case 'gstin':
                $validator = new SPP_Validator_GstinValidator($elem, $errHolder, $msg);
                $elem->setAttribute('pattern', '[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}');
                break;
            case 'ifsc':
                $validator = new SPP_Validator_IfscValidator($elem, $errHolder, $msg);
                $elem->setAttribute('pattern', '^[A-Z]{4}0[A-Z0-9]{6}$');
                break;
            case 'pincode':
                $validator = new SPP_Validator_PincodeValidator($elem, $errHolder, $msg);
                $elem->setAttribute('pattern', '^[1-9][0-9]{5}$');
                break;
            case 'indmobile':
                $validator = new SPP_Validator_IndiaMobileValidator($elem, $errHolder, $msg);
                $elem->setAttribute('pattern', '^[6-9]\d{9}$');
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
            case 'honeypot':
            case 'antispam':
                $validator = new SPP_Validator_HoneypotValidator($elem, $errHolder, $msg);
                break;
            case 'workflow_guard':
            case 'workflowguard':
            case 'guard':
                $entity = $vConfig['entity'] ?? $vConfig['entity_type'] ?? '';
                $state = $vConfig['state'] ?? $vConfig['required_state'] ?? '';
                $validator = new SPP_Validator_WorkflowGuardValidator($elem, $entity, $state, $errHolder, $msg);
                break;
        }

        // Custom Rule Registry fallback for YAML-registered rules
        if ($validator === null && \SPPMod\SPPView\Validation\CustomRuleRegistry::has($type)) {
            $params = [];
            if (isset($vConfig['value'])) {
                $params = is_array($vConfig['value']) ? $vConfig['value'] : [$vConfig['value']];
            } elseif (isset($vConfig['params'])) {
                $params = (array)$vConfig['params'];
            } elseif (isset($vConfig['options'])) {
                $params = (array)$vConfig['options'];
            }

            $rule = \SPPMod\SPPView\Validation\CustomRuleRegistry::resolve($type, $params);
            if ($rule) {
                $fieldName = $elem->getAttribute('name') ?: $elem->getAttribute('id') ?: 'field';
                $customMsg = $vConfig['message'] ?? null;

                $validator = new \SPPMod\SPPView\Validation\ViewValidatorRuleAdapter(
                    $rule,
                    $fieldName,
                    $elem,
                    $errHolder,
                    $customMsg
                );

                if (!isset($vConfig['message'])) {
                    $msg = $validator->getMsg();
                }

                // Auto-apply client HTML5 / SPP-UX attributes to the tag
                $clientConstraints = $rule->clientRule($fieldName);
                if (!empty($clientConstraints)) {
                    foreach ($clientConstraints as $cName => $cVal) {
                        if ($cVal === true) {
                            $elem->setAttribute($cName, $cName);
                        } elseif ($cVal !== false && $cVal !== null) {
                            $elem->setAttribute($cName, (string)$cVal);
                        }
                    }
                }
                if ($customMsg) {
                    $elem->setAttribute('data-msg', $customMsg);
                }
            }
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
        $form = self::fromYaml($yamlPath);
        $form->bind($data);
        $isValid = $form->isValid();
        return [
            'valid' => $isValid,
            'errors' => $form->getErrors()
        ];
    }
}
