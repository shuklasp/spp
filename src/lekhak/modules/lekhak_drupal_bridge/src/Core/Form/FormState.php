<?php
namespace Lekhak\Modules\LekhakDrupalBridge\Core\Form;

class FormState {
    protected $values = [];
    protected $errors = [];

    public function getValues() {
        return $this->values;
    }

    public function getValue($key, $default = null) {
        return $this->values[$key] ?? $default;
    }

    public function setValues(array $values) {
        $this->values = $values;
        return $this;
    }

    public function setValue($key, $value) {
        $this->values[$key] = $value;
        return $this;
    }

    public function setErrorByName($name, $message) {
        $this->errors[$name] = $message;
        return $this;
    }

    public function hasAnyErrors() {
        return !empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }
}
