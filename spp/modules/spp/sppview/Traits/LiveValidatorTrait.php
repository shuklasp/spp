<?php
declare(strict_types=1);

namespace SPPMod\SPPView\Traits;

use ReflectionClass;
use ReflectionProperty;
use SPPMod\SPPView\ValidationException;
use SPPMod\SPPView\LiveForm;
use SPPMod\SPPView\LiveComponent;

/**
 * Trait LiveValidatorTrait
 * 
 * Provides robust property validation for LiveComponents and LiveForms.
 * Supports deeply nested validation logic via dot notation.
 */
trait LiveValidatorTrait
{
    /** @var array<string, string> Validation error messages keyed by field name */
    public array $errors = [];

    /** @var array<string, string> Validation rules in 'field' => 'rule|rule' format */
    protected array $rules = [];

    /**
     * Validate public properties against rules.
     *
     * @param array|null $rules Optional override rules
     * @param string $prefix Optional prefix for nested form errors (internal use)
     * @throws ValidationException
     */
    public function validate(array $rules = null, string $prefix = ''): void
    {
        $this->errors = [];
        $meta = class_exists(LiveComponent::class) ? LiveComponent::getParsedAttributes(static::class) : ['validationRules' => []];
        $dynamicRules = [];
        if (method_exists($this, 'rules')) {
            $dyn = $this->rules();
            if (is_array($dyn)) $dynamicRules = $dyn;
        }
        $rulesToUse = $rules ?? array_merge($this->rules, $meta['validationRules'] ?? [], $dynamicRules);
        
        $this->executeValidation($rulesToUse, $prefix);
        
        // Cascade validation to nested LiveForm objects
        $refClass = new ReflectionClass($this);
        foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->isInitialized($this) ? $prop->getValue($this) : null;
            if ($value instanceof LiveForm) {
                try {
                    $formPrefix = $prefix ? "{$prefix}.{$prop->getName()}" : $prop->getName();
                    $value->validate(null, $formPrefix);
                } catch (ValidationException $e) {
                    // Merge nested form errors
                    foreach ($value->errors as $field => $msg) {
                        $this->errors[$field] = $msg;
                    }
                }
            }
        }
        
        if (!empty($this->errors)) {
            throw new ValidationException("Validation failed.");
        }
    }

    /**
     * Validate a single specific field. Supports dot notation for nested forms (e.g. 'form.title').
     *
     * @param string $field The field name
     * @param string $prefix Optional prefix (internal use)
     * @throws ValidationException
     */
    public function validateOnly(string $field, string $prefix = ''): void
    {
        $previousErrors = $this->errors;
        $this->errors = [];

        if (str_contains($field, '.')) {
            // Nested form validation
            $parts = explode('.', $field, 2);
            $formProp = $parts[0];
            $formField = $parts[1];
            
            if (property_exists($this, $formProp)) {
                $form = $this->$formProp;
                if ($form instanceof LiveForm) {
                    try {
                        $formPrefix = $prefix ? "{$prefix}.{$formProp}" : $formProp;
                        $form->validateOnly($formField, $formPrefix);
                    } catch (ValidationException $e) {
                        foreach ($form->errors as $f => $msg) {
                            $this->errors[$f] = $msg;
                        }
                    }
                }
            }
        } else {
            $meta = class_exists(LiveComponent::class) ? LiveComponent::getParsedAttributes(static::class) : ['validationRules' => []];
            $dynamicRules = [];
            if (method_exists($this, 'rules')) {
                $dyn = $this->rules();
                if (is_array($dyn)) $dynamicRules = $dyn;
            }
            $allRules = array_merge($this->rules, $meta['validationRules'] ?? [], $dynamicRules);

            if (isset($allRules[$field])) {
                $this->executeValidation([$field => $allRules[$field]], $prefix);
            }
        }

        if (!empty($this->errors)) {
            $previousErrors = array_merge($previousErrors, $this->errors);
            $this->errors = $previousErrors;
            throw new ValidationException("Validation failed for {$field}.");
        } else {
            unset($previousErrors[$field]);
            $this->errors = $previousErrors;
        }
    }

    /**
     * Manually add an error message.
     */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    /**
     * Clear validation errors.
     */
    public function resetValidation(string ...$fields): void
    {
        if (empty($fields)) {
            $this->errors = [];
        } else {
            foreach ($fields as $field) {
                unset($this->errors[$field]);
            }
        }
    }

    /**
     * Execute rules against properties.
     */
    protected function executeValidation(array $rules, string $prefix = ''): void
    {
        $allData = [];
        $ref = new ReflectionClass($this);
        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $p) {
            if ($p->isInitialized($this)) {
                $allData[$p->getName()] = $p->getValue($this);
            }
        }

        foreach ($rules as $field => $ruleDef) {
            $value = property_exists($this, $field) ? $this->$field : null;
            $rulesArr = is_array($ruleDef) ? $ruleDef : explode('|', (string)$ruleDef);
            $errorKey = $prefix ? "{$prefix}.{$field}" : $field;

            foreach ($rulesArr as $rule) {
                // 1. RuleInterface instance or registered CustomRuleRegistry alias
                $resolvedRule = null;
                if ($rule instanceof \SPPMod\SPPView\Validation\RuleInterface) {
                    $resolvedRule = $rule;
                } elseif (is_string($rule) && class_exists('\\SPPMod\\SPPView\\Validation\\CustomRuleRegistry') && \SPPMod\SPPView\Validation\CustomRuleRegistry::has($rule)) {
                    $resolvedRule = \SPPMod\SPPView\Validation\CustomRuleRegistry::resolve($rule);
                }

                if ($resolvedRule !== null) {
                    if (!$resolvedRule->passes($field, $value, $allData)) {
                        $this->errors[$errorKey] = $resolvedRule->message($field);
                        break;
                    }
                    continue;
                }

                // 2. Closure / callable rule
                if (is_callable($rule)) {
                    $passes = $rule($field, $value, $allData);
                    if (!$passes) {
                        $this->errors[$errorKey] = ucfirst($field) . " is invalid.";
                        break;
                    }
                    continue;
                }

                // 3. Built-in string rules
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $this->errors[$errorKey] = ucfirst($field) . " is required.";
                    break;
                }
                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$errorKey] = ucfirst($field) . " must be a valid email address.";
                    break;
                }
                if ($rule === 'numeric' && !is_numeric($value)) {
                    $this->errors[$errorKey] = ucfirst($field) . " must be a number.";
                    break;
                }
                if ($rule === 'string' && !is_string($value)) {
                    $this->errors[$errorKey] = ucfirst($field) . " must be a string.";
                    break;
                }
                if ($rule === 'integer' && !is_int($value) && !ctype_digit((string)$value)) {
                    $this->errors[$errorKey] = ucfirst($field) . " must be an integer.";
                    break;
                }
                if ($rule === 'boolean' && !is_bool($value) && $value !== 0 && $value !== 1 && $value !== '0' && $value !== '1') {
                    $this->errors[$errorKey] = ucfirst($field) . " must be true or false.";
                    break;
                }
                if ($rule === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->errors[$errorKey] = ucfirst($field) . " must be a valid URL.";
                    break;
                }
                if (is_string($rule) && str_starts_with($rule, 'in:')) {
                    $options = explode(',', substr($rule, 3));
                    if (!in_array((string)$value, $options, true)) {
                        $this->errors[$errorKey] = ucfirst($field) . " must be one of: " . implode(', ', $options) . ".";
                        break;
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'regex:')) {
                    $pattern = substr($rule, 6);
                    if (!preg_match($pattern, (string)$value)) {
                        $this->errors[$errorKey] = ucfirst($field) . " format is invalid.";
                        break;
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) < $min) {
                        $this->errors[$errorKey] = ucfirst($field) . " must be at least {$min} characters.";
                    } elseif (is_numeric($value) && $value < $min) {
                        $this->errors[$errorKey] = ucfirst($field) . " must be at least {$min}.";
                    } elseif (is_array($value) && count($value) < $min) {
                        $this->errors[$errorKey] = ucfirst($field) . " must have at least {$min} items.";
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) > $max) {
                        $this->errors[$errorKey] = ucfirst($field) . " must not exceed {$max} characters.";
                    } elseif (is_numeric($value) && $value > $max) {
                        $this->errors[$errorKey] = ucfirst($field) . " must not exceed {$max}.";
                    } elseif (is_array($value) && count($value) > $max) {
                        $this->errors[$errorKey] = ucfirst($field) . " must not have more than {$max} items.";
                    }
                }
            }
        }
    }
}
