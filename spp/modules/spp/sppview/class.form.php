<?php

namespace SPPMod\SPPView;

/**
 * class Form
 * 
 * Provides a Fluent API for building ViewForms programmatically,
 * vastly improving Developer Experience (DX) and IDE autocompletion
 * over traditional YAML definitions.
 */
class Form
{
    private array $config;

    public function __construct(string $name, ?string $action = null)
    {
        $this->config = [
            'form' => [
                'name' => $name,
                'method' => 'post'
            ],
            'elements' => [],
            'validators' => []
        ];
        if ($action !== null) {
            $this->config['form']['action'] = $action;
        }
    }

    /**
     * Start building a new form.
     * 
     * @param string $name The form name/id
     * @param string|null $action Optional submission action route
     * @return self
     */
    public static function make(string $name, ?string $action = null): self
    {
        return new self($name, $action);
    }

    /**
     * Alias for make() for backwards compatibility with stubs.
     * 
     * @param string $name
     * @param string|null $action
     * @return self
     */
    public static function create(string $name, ?string $action = null): self
    {
        return new self($name, $action);
    }

    /**
     * Build a ViewForm directly from a declarative YAML file path or YAML content string.
     *
     * @param string $yamlOrPath Absolute/relative YAML file path, form name, or raw YAML string.
     * @return ViewForm
     */
    public static function fromYaml(string $yamlOrPath, ?string $appname = null): ViewForm
    {
        if (file_exists($yamlOrPath)) {
            return ViewFormBuilder::fromYaml($yamlOrPath);
        }

        if (class_exists('\\SPPMod\\SPPView\\Forms')) {
            $form = Forms::get($yamlOrPath, $appname);
            if ($form instanceof ViewForm) {
                return $form;
            }
        }

        return ViewFormBuilder::fromString($yamlOrPath);
    }

    /**
     * Resolves a declarative form by name from the app/system registry.
     *
     * @param string $name
     * @param string|null $appname
     * @return ViewForm|null
     */
    public static function load(string $name, ?string $appname = null): ?ViewForm
    {
        if (class_exists('\\SPPMod\\SPPView\\Forms')) {
            return Forms::get($name, $appname);
        }
        return null;
    }

    /**
     * Directly validate data against a declarative YAML form definition.
     *
     * @param string $yamlOrPath
     * @param array $data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate(string $yamlOrPath, array $data): array
    {
        $form = self::fromYaml($yamlOrPath);
        $form->bind($data);
        $isValid = $form->isValid();
        return [
            'valid' => $isValid,
            'errors' => $form->getErrors()
        ];
    }

    /**
     * Attach a form-level validator (e.g. SPPWorkflowGuardValidator).
     *
     * @param object|string $validator
     * @param string $message
     * @return self
     */
    public function addValidator($validator, string $message = ''): self
    {
        $this->config['validators'][] = [
            'validator' => $validator,
            'message' => $message
        ];
        return $this;
    }

    public function setAction(string $action): self
    {
        $this->config['form']['action'] = $action;
        return $this;
    }

    public function setMethod(string $method): self
    {
        $this->config['form']['method'] = $method;
        return $this;
    }
    
    public function setService(string $service): self
    {
        $this->config['form']['service'] = $service;
        return $this;
    }

    public function addText(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'input',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        if (!empty($options['required'])) {
            $this->setRequired();
        }
        return $this;
    }

    public function addPassword(string $name, string $label = null): self
    {
        $this->config['elements'][$name] = [
            'name' => $name,
            'type' => 'password',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ];
        return $this;
    }

    public function addEmail(string $name, string $label = null): self
    {
        $this->config['elements'][$name] = [
            'name' => $name,
            'type' => 'input',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => [
                ['type' => 'email', 'message' => 'Invalid email address']
            ]
        ];
        return $this;
    }

    public function addSelect(string $name, array $options, string $label = null): self
    {
        $this->config['elements'][$name] = [
            'name' => $name,
            'type' => 'select',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'options' => $options,
            'validations' => []
        ];
        return $this;
    }

    public function addSubmit(string $name = 'submit', string $label = 'Submit'): self
    {
        $this->config['elements'][$name] = [
            'name' => $name,
            'type' => 'submit',
            'label' => $label,
            'validations' => []
        ];
        return $this;
    }

    /**
     * Mark the last added element as required.
     */
    public function setRequired(string $message = 'This field is required'): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem) {
            $this->config['elements'][$lastElem]['validations'][] = [
                'type' => 'required',
                'message' => $message
            ];
        }
        return $this;
    }

    /**
     * Set minimum length on the last added element.
     */
    public function setMinLength(int $min, string $message = 'Minimum length not met'): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem) {
            $this->config['elements'][$lastElem]['validations'][] = [
                'type' => 'min',
                'min' => $min,
                'message' => $message
            ];
        }
        return $this;
    }

    /**
     * Add a custom validation rule to the last added element.
     */
    public function addRule(string $type, array $params = [], string $message = 'Validation failed'): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem) {
            $validation = array_merge(['type' => $type, 'message' => $message], $params);
            $this->config['elements'][$lastElem]['validations'][] = $validation;
        }
        return $this;
    }

    public function addTextarea(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'textarea',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        if (!empty($options['required'])) {
            $this->setRequired();
        }
        return $this;
    }

    public function addCheckbox(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'checkbox',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        return $this;
    }

    public function addNumber(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'number',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        if (!empty($options['required'])) {
            $this->setRequired();
        }
        return $this;
    }

    public function addDate(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'date',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        if (!empty($options['required'])) {
            $this->setRequired();
        }
        return $this;
    }

    public function addFile(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'file',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        if (!empty($options['required'])) {
            $this->setRequired();
        }
        return $this;
    }

    public function addHidden(string $name, $value = null): self
    {
        $this->config['elements'][$name] = [
            'name' => $name,
            'type' => 'hidden',
            'value' => $value,
            'validations' => []
        ];
        return $this;
    }

    public function addSignature(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'signature',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        return $this;
    }

    public function addOTP(string $name, int $digits = 6, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'otp',
            'digits' => $digits,
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        return $this;
    }

    public function addTags(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'tags',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        return $this;
    }

    public function addRating(string $name, int $max = 5, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'rating',
            'max' => $max,
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        return $this;
    }

    public function addToggle(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'toggle',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        return $this;
    }

    public function addRadio(string $name, array $options, ?string $label = null, array $optionsConfig = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'radio',
            'options' => $options,
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $optionsConfig);
        return $this;
    }

    public function addEditor(string $name, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'editor',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        return $this;
    }

    public function addCode(string $name, string $language = 'php', ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'code',
            'language' => $language,
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        return $this;
    }

    public function addRepeater(string $name, array $fields, ?string $label = null, array $options = []): self
    {
        $this->config['elements'][$name] = array_merge([
            'name' => $name,
            'type' => 'repeater',
            'fields' => $fields,
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ], $options);
        return $this;
    }

    public function addHoneypot(string $name = 'sp_hp_trap', string $label = 'Leave this field blank'): self
    {
        $this->config['elements'][$name] = [
            'name' => $name,
            'type' => 'honeypot',
            'label' => $label,
            'validations' => []
        ];
        return $this;
    }

    /**
     * Enable workflow state transition action buttons on the form.
     * When bound to an entity implementing WorkflowableInterface,
     * it automatically determines available transitions from the current state.
     *
     * @param array|null $transitions Optional explicit transition definitions
     * @param array $options Options such as show_comment, etc.
     * @return self
     */
    public function addWorkflowActions(?array $transitions = null, array $options = []): self
    {
        $this->config['workflow_actions'] = [
            'transitions' => $transitions,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Attach conditional reactive visibility dependency to the last added element.
     *
     * @param string|array $fieldOrMap Parent field name or map of field => value(s)
     * @param mixed $operatorOrVal Comparison operator ('=', 'in') or target value
     * @param mixed $val Expected value when operator is supplied
     * @return self
     */
    public function dependsOn($fieldOrMap, $operatorOrVal = null, $val = null): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem) {
            $depConfig = [];
            if (is_array($fieldOrMap)) {
                $depConfig = $fieldOrMap;
            } elseif ($val !== null) {
                if (in_array($operatorOrVal, ['=', '==', 'eq'])) {
                    $depConfig = [$fieldOrMap => $val];
                } elseif (in_array($operatorOrVal, ['in', 'includes'])) {
                    $depConfig = [$fieldOrMap => (array)$val];
                } else {
                    $depConfig = [$fieldOrMap => $val];
                }
            } else {
                $depConfig = [$fieldOrMap => $operatorOrVal];
            }
            $this->config['elements'][$lastElem]['depends_on'] = $depConfig;
        }
        return $this;
    }

    /**
     * Attach real-time computed formula to the last added element.
     *
     * @param string $expression Formula e.g. "{price} * {quantity}" or "price * quantity"
     * @return self
     */
    public function computed(string $expression): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem) {
            // Auto-wrap plain variable identifiers if braces not already present
            if (!str_contains($expression, '{')) {
                $expression = preg_replace('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b(?!\()/', '{$1}', $expression);
            }
            $this->config['elements'][$lastElem]['computed'] = $expression;
        }
        return $this;
    }

    public function help(string $text): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem) {
            $this->config['elements'][$lastElem]['help'] = $text;
        }
        return $this;
    }

    public function placeholder(string $text): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem) {
            $this->config['elements'][$lastElem]['placeholder'] = $text;
        }
        return $this;
    }

    public function disabled(bool $disabled = true): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem && $disabled) {
            $this->config['elements'][$lastElem]['disabled'] = 'disabled';
        }
        return $this;
    }

    public function readonly(bool $readonly = true): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem && $readonly) {
            $this->config['elements'][$lastElem]['readonly'] = 'readonly';
        }
        return $this;
    }

    public function col(int|string $span): self
    {
        $lastElem = array_key_last($this->config['elements']);
        if ($lastElem) {
            $this->config['elements'][$lastElem]['col'] = (string)$span;
        }
        return $this;
    }

    private $boundEntity = null;

    /**
     * Bind an entity or data array to the form.
     *
     * @param object|array $entity
     * @return self
     */
    public function bind($entity): self
    {
        $this->boundEntity = $entity;
        return $this;
    }

    /**
     * Return raw config array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Build and return the final ViewForm object.
     */
    public function build(): ViewForm
    {
        $form = ViewFormBuilder::fromArray($this->config, $this->config['form']['name']);
        
        // Attach form-level validators
        foreach ($this->config['validators'] ?? [] as $valDef) {
            $val = $valDef['validator'];
            $msg = $valDef['message'] ?? '';
            if (is_string($val) && class_exists($val)) {
                $val = new $val();
            }
            if ($val instanceof ViewValidator) {
                $form->addValidator($val, $msg);
            }
        }

        // Auto-attach Honeypot validator if any honeypot element exists
        if (!class_exists('\\SPPHoneypotValidator')) {
            $valFile = __DIR__ . '/sppvalidator/classes.sppvalidators.php';
            if (file_exists($valFile)) {
                require_once $valFile;
            }
        }
        foreach ($this->config['elements'] as $elName => $elDef) {
            if (($elDef['type'] ?? '') === 'honeypot') {
                $hpElem = $form->getChild($elName);
                if (class_exists('\\SPPMod\\SPPView\\SPPHoneypotValidator')) {
                    $form->addValidator(new \SPPMod\SPPView\SPPHoneypotValidator($hpElem, 'honeypoterror', 'Spam submission detected.'));
                } elseif (class_exists('\\SPPHoneypotValidator')) {
                    $form->addValidator(new \SPPHoneypotValidator($hpElem, 'honeypoterror', 'Spam submission detected.'));
                }
            }
        }

        // Auto-attach Workflow action bar if requested or entity is Workflowable
        if (!empty($this->config['workflow_actions']) || ($this->boundEntity instanceof \SPP\Core\Interfaces\WorkflowableInterface && ($this->config['workflow_actions'] ?? null) !== false)) {
            $wfConfig = $this->config['workflow_actions'] ?? [];
            $explicitTransitions = $wfConfig['transitions'] ?? null;
            $options = $wfConfig['options'] ?? [];

            $transitions = $explicitTransitions;
            $currentState = 'draft';

            if ($this->boundEntity) {
                if ($transitions === null) {
                    $transitions = method_exists($this->boundEntity, 'getAvailableTransitions')
                        ? $this->boundEntity->getAvailableTransitions()
                        : (class_exists('\\SPPMod\\SPPWorkflow\\SPPWorkflowManager') ? \SPPMod\SPPWorkflow\SPPWorkflowManager::getAvailableTransitions($this->boundEntity) : []);
                }
                $currentState = method_exists($this->boundEntity, 'getWorkflowState')
                    ? (is_array($this->boundEntity->getWorkflowState()) ? ($this->boundEntity->getWorkflowState()[0] ?? 'draft') : (string)$this->boundEntity->getWorkflowState())
                    : ($this->boundEntity->status ?? 'draft');
            }

            if (!empty($transitions) || !empty($currentState)) {
                if (class_exists('\\SPPMod\\SPPView\\SPPViewForm_WorkflowActions')) {
                    $wfActions = new \SPPMod\SPPView\SPPViewForm_WorkflowActions(
                        '_workflow_actions',
                        $transitions ?? [],
                        (string)$currentState,
                        $options['show_comment'] ?? false
                    );
                    $form->addElement($wfActions);
                }
            }
        }

        if ($this->boundEntity !== null) {
            $form->bind($this->boundEntity);
        }

        return $form;
    }

    /**
     * Convenience method to build and render the form HTML.
     *
     * @param string|null $theme
     * @return string
     */
    public function render(?string $theme = null): string
    {
        $viewForm = $this->build();
        if ($theme) {
            $viewForm->setTheme($theme);
        }
        return $viewForm->getHTML();
    }
}
