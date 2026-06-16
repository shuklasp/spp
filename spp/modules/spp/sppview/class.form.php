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

    public function __construct(string $name)
    {
        $this->config = [
            'form' => [
                'name' => $name,
                'method' => 'post'
            ],
            'elements' => []
        ];
    }

    /**
     * Start building a new form.
     * 
     * @param string $name The form name/id
     * @return self
     */
    public static function make(string $name): self
    {
        return new self($name);
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

    public function addText(string $name, string $label = null): self
    {
        $this->config['elements'][$name] = [
            'name' => $name,
            'type' => 'input',
            'label' => $label ?? ucfirst(str_replace('_', ' ', $name)),
            'validations' => []
        ];
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

    /**
     * Build and return the final ViewForm object.
     */
    public function build(): ViewForm
    {
        return ViewFormBuilder::fromArray($this->config, $this->config['form']['name']);
    }
}
