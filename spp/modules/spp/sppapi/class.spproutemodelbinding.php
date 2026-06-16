<?php
namespace SPPMod\SPPAPI;

class SPPRouteModelBinding {
    
    /**
     * Resolves an {entity} ID from the route path to a loaded SPPEntity object.
     */
    public static function resolve(string $entityClass, string $id) {
        if (!class_exists($entityClass)) {
            throw new \SPP\Core\SPPException("Entity class not found for binding.");
        }
        
        try {
            $entity = new $entityClass($id);
            if (!$entity->get($entityClass::getMetadata('id_field', 'id'))) {
                throw new \SPP\Core\SPPException("Model not found.", 404);
            }
            return $entity;
        } catch (\SPP\Core\SPPException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \SPP\Core\SPPException("Model binding failed: " . $e->getMessage(), 404);
        }
    }
}
