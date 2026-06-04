<?php

namespace SPPMod\SPPView;

/**
 * Interface DataTransformer
 * Allows converting data between the Model (Entity) and the View (Form Element).
 */
interface DataTransformer
{
    public function transform(mixed $value): mixed; // Model to View
    public function reverseTransform(mixed $value): mixed; // View to Model
}

/**
 * Transforms a DateTime object to Y-m-d string and vice-versa.
 */
class DateTransformer implements DataTransformer
{
    public function transform(mixed $value): mixed
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }
        return $value;
    }
    public function reverseTransform(mixed $value): mixed
    {
        if (empty($value)) {
            return null;
        }
        return new \DateTime($value);
    }
}

/**
 * Transforms an array to a JSON string and vice-versa.
 */
class JsonTransformer implements DataTransformer
{
    public function transform(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        return $value;
    }
    public function reverseTransform(mixed $value): mixed
    {
        if (empty($value)) {
            return [];
        }
        return json_decode($value, true);
    }
}

/**
 * Transforms a comma-separated string to an array and vice-versa.
 */
class ArrayTransformer implements DataTransformer
{
    public function transform(mixed $value): mixed
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return $value;
    }
    public function reverseTransform(mixed $value): mixed
    {
        if (empty($value)) {
            return [];
        }
        return array_map('trim', explode(',', $value));
    }
}
