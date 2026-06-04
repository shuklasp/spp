<?php
namespace SPPMod\Sppapi;

class SPPRouteModelBinding {
    
    /**
     * Resolves an {entity} ID from the route path to a loaded SPPEntity object.
     */
    public static function resolve(string $entityClass, string $id) {
        if (!class_exists($entityClass)) {
            throw new \Exception("Entity class not found for binding.");
        }
        
        try {
            $entity = new $entityClass($id);
            if (!$entity->get($entityClass::getMetadata('id_field', 'id'))) {
                throw new \Exception("Model not found.", 404);
            }
            return $entity;
        } catch (\Exception $e) {
            throw new \Exception("Model binding failed: " . $e->getMessage(), 404);
        }
    }
}
