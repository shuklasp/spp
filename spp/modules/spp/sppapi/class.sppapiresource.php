<?php
namespace SPPMod\SPPAPI;

abstract class SPPApiResource
{

    /**
     * Transform an entity or array of data into the desired API structure.
     */
    abstract public function toArray($request): array;

    public static function collection(array $items, $request = null): array
    {
        $static = new static();
        return array_map(function ($item) use ($static, $request) {
            // Apply the transformation to the object if it's an entity, 
            // or pass it straight through if it's an array.
            if (is_object($item)) {
                // If it's an SPPEntity, we can temporarily set it on the resource
                $static->resource = $item;
                return $static->toArray($request);
            }
            return $item;
        }, $items);
    }
}
