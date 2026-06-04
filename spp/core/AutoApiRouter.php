<?php

namespace SPP\Core;

/**
 * AutoApiRouter
 * Automatically serves REST endpoints for SPPEntity definitions.
 */
class AutoApiRouter
{
    public static function handle()
    {
        // Require API Key by default, can be toggled via config
        $requireAuth = \SPP\App::getGlobalSettings('api.require_key') ?? true;
        
        header('Content-Type: application/json; charset=utf-8');

        if ($requireAuth) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (!str_starts_with($authHeader, 'Bearer ') && !isset($_GET['api_key'])) {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Unauthorized. API Key required."]);
                exit;
            }
            // Logic to validate API key would go here...
        }

        $q = $_GET['q'] ?? '';
        // q should be like 'api/v1/teacher' or 'api/v1/teacher/1'
        $parts = explode('/', trim($q, '/'));
        
        if (count($parts) < 3) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid API route."]);
            exit;
        }

        $entityNameRaw = $parts[2];
        $entityId = $parts[3] ?? null;
        
        // Convert to PascalCase for class matching
        $entityName = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $entityNameRaw)));

        // Look up entity definition
        $context = \SPP\Scheduler::getContext() ?: 'default';
        $entityPath = SPP_APP_DIR . "/src/{$context}/entities/" . strtolower($entityNameRaw) . ".yml";

        if (!file_exists($entityPath)) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Entity {$entityName} not found."]);
            exit;
        }
        
        $config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($entityPath));
        if (empty($config['enable_api'])) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "API access is not enabled for {$entityName}."]);
            exit;
        }
        
        $className = "\\App\\Entities\\{$entityName}";
        if (!class_exists($className)) {
            $phpPath = SPP_APP_DIR . "/src/{$context}/entities/entity." . strtolower($entityNameRaw) . ".php";
            if (!file_exists($phpPath)) $phpPath = SPP_APP_DIR . "/src/{$context}/entities/" . strtolower($entityName) . ".php";
            if (file_exists($phpPath)) require_once $phpPath;
        }

        if (!class_exists($className)) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Entity class {$className} could not be loaded."]);
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];

        try {
            switch ($method) {
                case 'GET':
                    if ($entityId) {
                        $obj = new $className($entityId);
                        if ($obj->id) {
                            echo json_encode(["status" => "success", "data" => $obj]);
                        } else {
                            http_response_code(404);
                            echo json_encode(["status" => "error", "message" => "Not found"]);
                        }
                    } else {
                        $list = $className::find_all();
                        echo json_encode(["status" => "success", "data" => $list]);
                    }
                    break;
                case 'POST':
                    $input = json_decode(file_get_contents('php://input'), true) ?? [];
                    $obj = new $className();
                    foreach ($input as $k => $v) $obj->$k = $v;
                    $obj->save();
                    echo json_encode(["status" => "success", "message" => "Created", "data" => $obj]);
                    break;
                case 'PUT':
                case 'PATCH':
                    $input = json_decode(file_get_contents('php://input'), true) ?? [];
                    $obj = new $className($entityId);
                    if (!$obj->id) {
                        http_response_code(404);
                        echo json_encode(["status" => "error", "message" => "Not found"]);
                        exit;
                    }
                    foreach ($input as $k => $v) $obj->$k = $v;
                    $obj->save();
                    echo json_encode(["status" => "success", "message" => "Updated", "data" => $obj]);
                    break;
                case 'DELETE':
                    $obj = new $className($entityId);
                    if ($obj->id) $obj->delete();
                    echo json_encode(["status" => "success", "message" => "Deleted"]);
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
                    break;
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        
        exit;
    }
}
