<?php
declare(strict_types=1);

namespace SPPMod\SPPView;

use ReflectionClass;
use ReflectionProperty;
use Exception;
use SPPMod\SPPView\Traits\LiveValidatorTrait;

/**
 * SPP LiveForm
 * Represents a group of state properties for a LiveComponent.
 */
abstract class LiveForm
{
    use LiveValidatorTrait;

    /**
     * Define validation rules for the form properties.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Define custom validation messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Reset form fields to their default values.
     *
     * @param string ...$fields
     * @return void
     */
    public function reset(string ...$fields): void
    {
        $defaults = $this->getDefaults();
        $propsToReset = empty($fields) ? array_keys($defaults) : $fields;

        foreach ($propsToReset as $prop) {
            if (array_key_exists($prop, $defaults)) {
                $this->{$prop} = $defaults[$prop];
            }
        }
    }

    /**
     * Reset all form fields except the specified ones.
     *
     * @param string ...$except
     * @return void
     */
    public function resetExcept(string ...$except): void
    {
        $defaults = $this->getDefaults();
        foreach ($defaults as $prop => $value) {
            if (!in_array($prop, $except)) {
                $this->{$prop} = $value;
            }
        }
    }

    /**
     * Fill the form fields with the given source array or object.
     *
     * @param object|array $source
     * @return void
     */
    public function fill(object|array $source): void
    {
        $data = is_object($source) ? get_object_vars($source) : $source;
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Dehydrate form public properties to array state.
     *
     * @return array
     */
    public function dehydrate(): array
    {
        $state = [];
        $refClass = new ReflectionClass($this);
        foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $state[$prop->getName()] = $prop->getValue($this);
        }
        return $state;
    }

    /**
     * Hydrate form state from array.
     *
     * @param array $state
     * @return void
     */
    public function hydrate(array $state): void
    {
        $refClass = new ReflectionClass($this);
        foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (array_key_exists($name, $state)) {
                $prop->setValue($this, $state[$name]);
            }
        }
    }

    /**
     * Get default values of public properties.
     *
     * @return array
     */
    protected function getDefaults(): array
    {
        $refClass = new ReflectionClass($this);
        $defaults = $refClass->getDefaultProperties();
        $publicProps = [];
        foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $publicProps[$prop->getName()] = $defaults[$prop->getName()] ?? null;
        }
        return $publicProps;
    }
}
