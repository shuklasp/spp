<?php
namespace Lekhak\Modules\LekhakDrupalApi\Controller;

use SPPMod\SPPDB\SPPDB;

class GenericEntityController
{

    private $db;

    public function __construct()
    {
        $this->db = new SPPDB();
    }

    public function getEntity($entityType, $bundle, $uuid)
    {
        $sql = "SELECT * FROM lek_entities WHERE entity_type = ? AND bundle = ? AND uuid = ? LIMIT 1";
        $results = $this->db->execute_query($sql, [$entityType, $bundle, $uuid]);

        if (empty($results)) {
            http_response_code(404);
            return json_encode(["errors" => [["title" => "Not Found", "status" => "404"]]]);
        }

        $row = $results[0];
        return json_encode($this->formatResponse(json_decode($row['data'], true), $row['uuid'], $entityType, $bundle), JSON_UNESCAPED_SLASHES);
    }

    public function getEntities($entityType, $bundle)
    {
        $sql = "SELECT * FROM lek_entities WHERE entity_type = ? AND bundle = ? LIMIT 50";
        $results = $this->db->execute_query($sql, [$entityType, $bundle]);

        $data = [];
        foreach ($results as $row) {
            $data[] = $this->formatData(json_decode($row['data'], true), $row['uuid'], $entityType, $bundle);
        }

        return json_encode($this->wrapDocument($data, true), JSON_UNESCAPED_SLASHES);
    }

    public function createEntity($entityType, $bundle)
    {
        if (empty($_SESSION['uid'])) {
            http_response_code(401);
            return json_encode(["errors" => [["title" => "Unauthorized", "status" => "401"]]]);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['data']['attributes'])) {
            http_response_code(400);
            return json_encode(["errors" => [["title" => "Bad Request", "status" => "400"]]]);
        }

        $uuid = $this->generateUuid();
        $created = date('Y-m-d H:i:s');
        $dataStr = json_encode($input['data']);

        $sql = "INSERT INTO lek_entities (uuid, entity_type, bundle, created, changed, data) VALUES (?, ?, ?, ?, ?, ?)";
        $this->db->execute_query($sql, [$uuid, $entityType, $bundle, $created, $created, $dataStr]);

        http_response_code(201);
        return $this->getEntity($entityType, $bundle, $uuid);
    }

    public function updateEntity($entityType, $bundle, $uuid)
    {
        if (empty($_SESSION['uid'])) {
            http_response_code(401);
            return json_encode(["errors" => [["title" => "Unauthorized", "status" => "401"]]]);
        }

        // Check if exists
        $sql = "SELECT data FROM lek_entities WHERE entity_type = ? AND bundle = ? AND uuid = ? LIMIT 1";
        $results = $this->db->execute_query($sql, [$entityType, $bundle, $uuid]);
        if (empty($results)) {
            http_response_code(404);
            return json_encode(["errors" => [["title" => "Not Found", "status" => "404"]]]);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['data']['attributes'])) {
            http_response_code(400);
            return json_encode(["errors" => [["title" => "Bad Request", "status" => "400"]]]);
        }

        $existingData = json_decode($results[0]['data'], true);
        // Merge attributes
        if (isset($input['data']['attributes'])) {
            $existingData['attributes'] = array_merge($existingData['attributes'] ?? [], $input['data']['attributes']);
        }
        if (isset($input['data']['relationships'])) {
            $existingData['relationships'] = array_merge($existingData['relationships'] ?? [], $input['data']['relationships']);
        }

        $dataStr = json_encode($existingData);
        $changed = date('Y-m-d H:i:s');

        $updateSql = "UPDATE lek_entities SET data = ?, changed = ? WHERE entity_type = ? AND bundle = ? AND uuid = ?";
        $this->db->execute_query($updateSql, [$dataStr, $changed, $entityType, $bundle, $uuid]);

        return $this->getEntity($entityType, $bundle, $uuid);
    }

    public function deleteEntity($entityType, $bundle, $uuid)
    {
        if (empty($_SESSION['uid'])) {
            http_response_code(401);
            return json_encode(["errors" => [["title" => "Unauthorized", "status" => "401"]]]);
        }

        $sql = "DELETE FROM lek_entities WHERE entity_type = ? AND bundle = ? AND uuid = ?";
        $this->db->execute_query($sql, [$entityType, $bundle, $uuid]);

        http_response_code(204);
        return '';
    }

    private function generateUuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    private function formatData($data, $uuid, $entityType, $bundle)
    {
        $baseUrl = rtrim(\SPP\App::getBaseUrl(), '/');
        return [
            'type' => "{$entityType}--{$bundle}",
            'id' => $uuid,
            'attributes' => $data['attributes'] ?? [],
            'relationships' => $data['relationships'] ?? [],
            'links' => [
                'self' => [
                    'href' => $baseUrl . "/jsonapi/{$entityType}/{$bundle}/{$uuid}"
                ]
            ]
        ];
    }

    private function formatResponse($data, $uuid, $entityType, $bundle)
    {
        return $this->wrapDocument($this->formatData($data, $uuid, $entityType, $bundle), false);
    }

    private function wrapDocument($data, $isCollection = false)
    {
        return [
            'jsonapi' => [
                'version' => '1.0',
                'meta' => [
                    'links' => [
                        'self' => ['href' => 'http://jsonapi.org/format/1.0/']
                    ]
                ]
            ],
            'data' => $data,
            'links' => [
                'self' => [
                    'href' => $_SERVER['REQUEST_URI'] ?? ''
                ]
            ]
        ];
    }
}
