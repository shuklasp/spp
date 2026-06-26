<?php
namespace SPPMod\Lekhak\Serv;

use SPPMod\SPPAuth\SPPAuth;
use SPPMod\SPPDB\SppEntity;

/**
 * Class ApiController
 * Provides headless JSON:API capabilities for all Lekhak entities.
 */
class ApiController
{
    /**
     * Map friendly type names to Entity classes.
     */
    protected function resolveEntityClass(string $type): ?string
    {
        $map = [
            'node' => '\\SPPMod\\Lekhak\\Core\\LekhakNode',
            'user' => '\\SPPMod\\Lekhak\\Core\\LekhakUser',
            'comment' => '\\SPPMod\\Lekhak\\Core\\LekhakComment',
            'taxonomy' => '\\SPPMod\\Lekhak\\Core\\LekhakTaxonomyTerm',
            'forum' => '\\SPPMod\\Lekhak\\Core\\LekhakForumTopic',
        ];

        return $map[strtolower($type)] ?? null;
    }

    /**
     * Helper to send JSON response.
     */
    protected function jsonResponse(array $data, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Clean entity data for public API consumption.
     */
    protected function serializeEntity(SppEntity $entity): array
    {
        $data = $entity->toArray();

        // Remove internal properties that shouldn't be exposed
        unset($data['_table']);
        unset($data['storage_strategy']);

        // Ensure complex metadata is decoded if it's JSON
        if (isset($data['metadata']) && is_string($data['metadata'])) {
            $decoded = json_decode($data['metadata'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['metadata'] = $decoded;
            }
        }

        return $data;
    }

    /**
     * Authenticate API requests.
     * For now, requires active session, but could be extended to JWT or Bearer token.
     */
    protected function authenticate(): bool
    {
        return SPPAuth::check();
    }

    /**
     * Route handler for /api/v1/entity/{type}[/{id}]
     */
    public function handleRequest()
    {
        $uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($uri, PHP_URL_PATH);

        // Extract type and id from path: e.g. /school1/lekhak/api/v1/entity/node/5
        preg_match('#/api/v1/entity/([^/]+)(?:/(\d+))?#', $path, $matches);

        if (count($matches) < 2) {
            $this->jsonResponse(['error' => 'Invalid endpoint format.'], 400);
        }

        $type = $matches[1];
        $id = $matches[2] ?? null;

        $class = $this->resolveEntityClass($type);
        if (!$class || !class_exists($class)) {
            $this->jsonResponse(['error' => "Entity type '{$type}' not found."], 404);
        }

        $method = $_SERVER['REQUEST_METHOD'];

        try {
            switch ($method) {
                case 'GET':
                    if ($id) {
                        $this->getEntity($class, $id);
                    } else {
                        $this->listEntities($class);
                    }
                    break;
                case 'POST':
                    $this->createEntity($class);
                    break;
                case 'PATCH':
                case 'PUT':
                    if (!$id) {
                        $this->jsonResponse(['error' => 'ID required for update.'], 400);
                    }
                    $this->updateEntity($class, $id);
                    break;
                case 'DELETE':
                    if (!$id) {
                        $this->jsonResponse(['error' => 'ID required for deletion.'], 400);
                    }
                    $this->deleteEntity($class, $id);
                    break;
                default:
                    $this->jsonResponse(['error' => 'Method not allowed.'], 405);
            }
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    protected function getEntity(string $class, $id)
    {
        $entity = $class::find($id);
        if (!$entity) {
            $this->jsonResponse(['error' => 'Entity not found.'], 404);
        }

        // Check access
        if (method_exists($entity, 'checkAccess') && !$entity->checkAccess('view')) {
            $this->jsonResponse(['error' => 'Access denied.'], 403);
        }

        $this->jsonResponse(['data' => $this->serializeEntity($entity)]);
    }

    protected function listEntities(string $class)
    {
        // Simple list endpoint. In a real system, we'd add pagination and filtering query parameters here.
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);

        // Assuming there's a simple query method or we can use raw DB.
        // For SppEntity, we might need SppEntityQuery, but let's use a basic find approach if possible,
        // or just use DB query and instantiate.
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = $class::getMetadata('table');
        $idField = $class::getMetadata('id_field', 'id');

        $rows = $db->execute_query("SELECT $idField FROM $table LIMIT $limit OFFSET $offset");

        $entities = [];
        foreach ($rows as $row) {
            $entity = $class::find($row[$idField]);
            if ($entity && (!method_exists($entity, 'checkAccess') || $entity->checkAccess('view'))) {
                $entities[] = $this->serializeEntity($entity);
            }
        }

        $this->jsonResponse(['data' => $entities, 'meta' => ['limit' => $limit, 'offset' => $offset]]);
    }

    protected function createEntity(string $class)
    {
        if (!$this->authenticate()) {
            $this->jsonResponse(['error' => 'Unauthorized.'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON payload.'], 400);
        }

        $entity = new $class();

        // Check access if generic create access is implemented, or just check role
        if (!\SPPMod\SPPAuth\SPPAuth::hasRight('create content')) { // Simple fallback
            $this->jsonResponse(['error' => 'Access denied.'], 403);
        }

        foreach ($input as $key => $value) {
            // Prevent overriding protected fields
            if (!in_array($key, ['id', 'created', 'changed', '_table', 'storage_strategy'])) {
                $entity->{$key} = $value;
            }
        }

        $entity->save();
        $this->jsonResponse(['data' => $this->serializeEntity($entity)], 201);
    }

    protected function updateEntity(string $class, $id)
    {
        if (!$this->authenticate()) {
            $this->jsonResponse(['error' => 'Unauthorized.'], 401);
        }

        $entity = $class::find($id);
        if (!$entity) {
            $this->jsonResponse(['error' => 'Entity not found.'], 404);
        }

        if (method_exists($entity, 'checkAccess') && !$entity->checkAccess('update')) {
            $this->jsonResponse(['error' => 'Access denied.'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON payload.'], 400);
        }

        foreach ($input as $key => $value) {
            if (!in_array($key, ['id', 'created', 'changed', '_table', 'storage_strategy'])) {
                $entity->{$key} = $value;
            }
        }

        $entity->save();
        $this->jsonResponse(['data' => $this->serializeEntity($entity)]);
    }

    protected function deleteEntity(string $class, $id)
    {
        if (!$this->authenticate()) {
            $this->jsonResponse(['error' => 'Unauthorized.'], 401);
        }

        $entity = $class::find($id);
        if (!$entity) {
            $this->jsonResponse(['error' => 'Entity not found.'], 404);
        }

        if (method_exists($entity, 'checkAccess') && !$entity->checkAccess('delete')) {
            $this->jsonResponse(['error' => 'Access denied.'], 403);
        }

        $entity->delete();
        $this->jsonResponse(['message' => 'Entity deleted successfully.']);
    }
}
