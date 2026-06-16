<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Controllers;

use SPPMod\SPPAPI\SPPApiResponse;

class EntityPostController
{
    public static function handle(string $entityName, string $classMap): void
    {
        if (empty($_SERVER['HTTP_X_SPP_API']) || $_SERVER['HTTP_X_SPP_API'] !== '1') {
            SPPApiResponse::error('CSRF Protection: Missing X-SPP-API header.', 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            SPPApiResponse::error('Invalid JSON payload.', 400);
        }

        $entity = new $classMap();
        if ($classMap === "\\SPPMod\\SPPEntity\\SPPEntity") {
            $entity->setTable(\SPPMod\SppDb\SPPEntity::getMetadata('table'));
        }
        if (method_exists($entity, 'checkAccess') && !$entity->checkAccess('create')) {
            SPPApiResponse::error('Access denied.', 403);
        }

        self::fillEntity($entity, $classMap, $input);

        if (method_exists($entity, 'save')) {
            $entity->save();
        } else {
            SPPApiResponse::error('Entity does not support saving via standard API.', 500);
        }

        $resourceClass = EntityGetController::resolveResourceClass($entityName);
        $data = $entity->jsonSerialize();
        if ($resourceClass) {
            $resObj = new $resourceClass();
            $resObj->resource = $entity;
            $data = $resObj->toArray(null);
        }

        SPPApiResponse::success($data, 'Created', 201);
    }

    public static function fillEntity(object $entity, string $classMap, array $input): void
    {
        $fillable = [];
        if (method_exists($classMap, 'getMetadata')) {
            $fillable = $classMap::getMetadata('attributes') ?: [];
        }
        $fillableKeys = is_array($fillable) ? array_keys($fillable) : [];

        foreach ($input as $key => $value) {
            if (in_array($key, ['id', 'created', 'changed', '_table', 'storage_strategy'], true)) {
                continue;
            }
            if (empty($fillableKeys) || !in_array($key, $fillableKeys, true)) {
                continue;
            }
            $entity->{$key} = $value;
        }
    }
}
