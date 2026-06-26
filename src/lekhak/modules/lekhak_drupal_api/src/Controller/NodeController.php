<?php
namespace Lekhak\Modules\LekhakDrupalApi\Controller;

use Lekhak\Modules\LekhakDrupalApi\Serializer\JsonApiSerializer;
use SPPMod\SPPDB\SPPDB;

class NodeController
{

    private $db;

    public function __construct()
    {
        $this->db = new SPPDB();
    }

    public function getNodeByUuid($type, $uuidOrId)
    {
        $id = $this->resolveIdFromUuid($uuidOrId);

        $results = $this->db->execute_query("SELECT * FROM lek_nodes WHERE id = ? LIMIT 1", [$id]);
        if (empty($results)) {
            return null;
        }

        $node = $results[0];
        if ($type && isset($node['bundle']) && $node['bundle'] !== $type) {
            return null;
        }

        $serialized = JsonApiSerializer::serializeNode($node, $type);
        return json_encode(JsonApiSerializer::wrapDocument($serialized, false), JSON_UNESCAPED_SLASHES);
    }

    public function getNodes($type)
    {
        $results = $this->db->execute_query("SELECT * FROM lek_nodes WHERE bundle = ? LIMIT 50", [$type]);

        $data = [];
        foreach ($results as $node) {
            $data[] = JsonApiSerializer::serializeNode($node, $type);
        }

        return json_encode(JsonApiSerializer::wrapDocument($data, true), JSON_UNESCAPED_SLASHES);
    }

    public function getRestNode($id)
    {
        $results = $this->db->execute_query("SELECT * FROM lek_nodes WHERE id = ? LIMIT 1", [(int) $id]);
        if (empty($results)) {
            return null;
        }

        return json_encode(JsonApiSerializer::serializeRestNode($results[0]), JSON_UNESCAPED_SLASHES);
    }

    public function createNode($bundle)
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

        $attrs = $input['data']['attributes'];
        $title = $attrs['title'] ?? 'Untitled';
        $body = $attrs['body']['value'] ?? '';
        $status = $attrs['status'] ?? 1;
        $author_id = $_SESSION['uid'];
        $created = date('Y-m-d H:i:s');

        $sql = "INSERT INTO lek_nodes (title, body, bundle, author_id, status, created, changed) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute_query($sql, [$title, $body, $bundle, $author_id, $status, $created, $created]);
        $newId = $this->db->getPDO()->lastInsertId();

        http_response_code(201);
        return $this->getNodeByUuid($bundle, $newId);
    }

    public function updateNode($bundle, $uuid)
    {
        if (empty($_SESSION['uid'])) {
            http_response_code(401);
            return json_encode(["errors" => [["title" => "Unauthorized", "status" => "401"]]]);
        }

        $id = $this->resolveIdFromUuid($uuid);
        if (!$id) {
            http_response_code(404);
            return json_encode(["errors" => [["title" => "Not Found", "status" => "404"]]]);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['data']['attributes'])) {
            http_response_code(400);
            return json_encode(["errors" => [["title" => "Bad Request", "status" => "400"]]]);
        }

        $attrs = $input['data']['attributes'];
        $updates = [];
        $params = [];

        if (isset($attrs['title'])) {
            $updates[] = "title = ?";
            $params[] = $attrs['title'];
        }
        if (isset($attrs['body']['value'])) {
            $updates[] = "body = ?";
            $params[] = $attrs['body']['value'];
        }
        if (isset($attrs['status'])) {
            $updates[] = "status = ?";
            $params[] = $attrs['status'];
        }

        if (!empty($updates)) {
            $updates[] = "changed = ?";
            $params[] = date('Y-m-d H:i:s');

            $params[] = $id; // For WHERE clause
            $sql = "UPDATE lek_nodes SET " . implode(", ", $updates) . " WHERE id = ?";
            $this->db->execute_query($sql, $params);
        }

        return $this->getNodeByUuid($bundle, $id);
    }

    public function deleteNode($bundle, $uuid)
    {
        if (empty($_SESSION['uid'])) {
            http_response_code(401);
            return json_encode(["errors" => [["title" => "Unauthorized", "status" => "401"]]]);
        }

        $id = $this->resolveIdFromUuid($uuid);
        if (!$id) {
            http_response_code(404);
            return json_encode(["errors" => [["title" => "Not Found", "status" => "404"]]]);
        }

        $sql = "DELETE FROM lek_nodes WHERE id = ?";
        $this->db->execute_query($sql, [$id]);

        http_response_code(204);
        return '';
    }

    private function resolveIdFromUuid($uuidOrId)
    {
        if (is_numeric($uuidOrId)) {
            return (int) $uuidOrId;
        }

        $results = $this->db->execute_query("SELECT id FROM lek_nodes ORDER BY id DESC LIMIT 1000");
        foreach ($results as $row) {
            $hash = md5('lekhak_entity_node_' . $row['id']);
            $fakeUuid = substr($hash, 0, 8) . '-' .
                substr($hash, 8, 4) . '-4' .
                substr($hash, 13, 3) . '-8' .
                substr($hash, 17, 3) . '-' .
                substr($hash, 20, 12);
            if ($fakeUuid === $uuidOrId) {
                return (int) $row['id'];
            }
        }

        return 0;
    }
}
