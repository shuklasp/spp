<?php

namespace SPPMod\Drishyam;

/**
 * Lightweight Polyfill for Laravel's ComponentAttributeBag
 */
class ComponentAttributeBag implements \ArrayAccess, \IteratorAggregate, \Stringable
{
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        // Filter out framework variables and non-scalars
        $ignored = ['__env', 'app', 'errors', 'loop', 'obLevel', '_SERVER', '_GET', '_POST', '_REQUEST', '_ENV', '_COOKIE', '_FILES', 'GLOBALS'];
        
        foreach ($attributes as $key => $value) {
            if (in_array($key, $ignored, true)) {
                continue;
            }
            if (is_scalar($value) || is_null($value)) {
                $this->attributes[$key] = $value;
            }
        }
    }

    public function merge(array $defaults): self
    {
        $merged = $this->attributes;

        foreach ($defaults as $key => $value) {
            if ($key === 'class') {
                $currentClass = $merged['class'] ?? '';
                $merged['class'] = trim($currentClass . ' ' . TemplateMacros::toCssClasses($value));
            } elseif (!array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }

        return new static($merged);
    }

    public function class(array|string $classes): self
    {
        return $this->merge(['class' => $classes]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function get(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function exceptProps(array $keys): self
    {
        $attributes = $this->attributes;
        foreach ($keys as $key) {
            unset($attributes[$key]);
        }
        return new static($attributes);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->attributes);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->attributes[] = $value;
        } else {
            $this->attributes[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function __toString(): string
    {
        $html = [];
        foreach ($this->attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) $html[] = $key;
            } elseif (!is_null($value)) {
                $html[] = $key . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        return implode(' ', $html);
    }
}
