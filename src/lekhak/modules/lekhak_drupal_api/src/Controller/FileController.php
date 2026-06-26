<?php
namespace Lekhak\Modules\LekhakDrupalApi\Controller;

use SPPMod\SPPDB\SPPDB;

class FileController
{

    private $db;

    public function __construct()
    {
        $this->db = new SPPDB();
    }

    public function getFile($uuid)
    {
        $sql = "SELECT * FROM lek_files WHERE uuid = ? LIMIT 1";
        $results = $this->db->execute_query($sql, [$uuid]);

        if (empty($results)) {
            http_response_code(404);
            return json_encode(["errors" => [["title" => "Not Found", "status" => "404"]]]);
        }

        $row = $results[0];
        return json_encode($this->formatResponse($row), JSON_UNESCAPED_SLASHES);
    }

    public function getFiles()
    {
        $sql = "SELECT * FROM lek_files LIMIT 50";
        $results = $this->db->execute_query($sql, []);

        $data = [];
        foreach ($results as $row) {
            $data[] = $this->formatData($row);
        }

        return json_encode($this->wrapDocument($data, true), JSON_UNESCAPED_SLASHES);
    }

    public function createFile()
    {
        if (empty($_SESSION['uid'])) {
            http_response_code(401);
            return json_encode(["errors" => [["title" => "Unauthorized", "status" => "401"]]]);
        }

        $mediaDir = dirname(__DIR__, 5) . '/var/media/lekhni';
        if (!is_dir($mediaDir)) {
            @mkdir($mediaDir, 0755, true);
        }

        $filename = 'upload_' . time();
        $binaryData = '';

        // Handle JSON:API Content-Disposition raw upload
        $contentDisposition = $_SERVER['HTTP_CONTENT_DISPOSITION'] ?? '';
        if (preg_match('/filename="([^"]+)"/', $contentDisposition, $matches)) {
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $matches[1]);
            $binaryData = file_get_contents('php://input');
        } elseif (!empty($_FILES['file'])) {
            // Fallback for standard multipart upload
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['file']['name']);
            $binaryData = file_get_contents($_FILES['file']['tmp_name']);
        } else {
            // Maybe they passed a JSON with attributes
            $input = json_decode(file_get_contents('php://input'), true);
            if (!empty($input['data']['attributes']['filename'])) {
                // Not a real upload, just creating the metadata
                $filename = $input['data']['attributes']['filename'];
                $uri = $input['data']['attributes']['uri']['value'] ?? 'public://' . $filename;
                $filemime = $input['data']['attributes']['filemime'] ?? 'application/octet-stream';
                $filesize = $input['data']['attributes']['filesize'] ?? 0;
                return $this->insertDbRecord($filename, $uri, $filemime, $filesize);
            }

            http_response_code(400);
            return json_encode(["errors" => [["title" => "Bad Request. Missing file data.", "status" => "400"]]]);
        }

        // Save file physically
        $filename = time() . '_' . $filename;
        $dest = $mediaDir . '/' . $filename;
        file_put_contents($dest, $binaryData);

        $uri = 'public://' . $filename; // Drupal standard

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $filemime = finfo_file($finfo, $dest);
        finfo_close($finfo);

        $filesize = filesize($dest);

        return $this->insertDbRecord($filename, $uri, $filemime, $filesize);
    }

    private function insertDbRecord($filename, $uri, $filemime, $filesize)
    {
        $uuid = $this->generateUuid();
        $created = date('Y-m-d H:i:s');

        $sql = "INSERT INTO lek_files (uuid, filename, uri, filemime, filesize, status, created, changed) VALUES (?, ?, ?, ?, ?, 1, ?, ?)";
        $this->db->execute_query($sql, [$uuid, $filename, $uri, $filemime, $filesize, $created, $created]);

        http_response_code(201);
        return $this->getFile($uuid);
    }

    public function deleteFile($uuid)
    {
        if (empty($_SESSION['uid'])) {
            http_response_code(401);
            return json_encode(["errors" => [["title" => "Unauthorized", "status" => "401"]]]);
        }

        $sql = "SELECT filename FROM lek_files WHERE uuid = ?";
        $results = $this->db->execute_query($sql, [$uuid]);
        if (!empty($results)) {
            $filename = $results[0]['filename'];
            $mediaDir = dirname(__DIR__, 5) . '/var/media/lekhni';
            $dest = $mediaDir . '/' . preg_replace('/^public:\/\//', '', $filename);
            if (file_exists($dest)) {
                @unlink($dest);
            }
        }

        $sql = "DELETE FROM lek_files WHERE uuid = ?";
        $this->db->execute_query($sql, [$uuid]);

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

    private function formatData($row)
    {
        $baseUrl = rtrim(\SPP\App::getBaseUrl(), '/');
        $publicUrl = rtrim(\SPP\App::getBaseUrl(), '/') . '/lekhak/var/media/lekhni/' . preg_replace('/^public:\/\//', '', $row['filename']);
        return [
            'type' => "file--file",
            'id' => $row['uuid'],
            'attributes' => [
                'drupal_internal__fid' => $row['id'],
                'filename' => $row['filename'],
                'uri' => [
                    'value' => $row['uri'],
                    'url' => $publicUrl
                ],
                'filemime' => $row['filemime'],
                'filesize' => $row['filesize'],
                'status' => true,
                'created' => gmdate('Y-m-d\TH:i:sP', strtotime($row['created'])),
                'changed' => gmdate('Y-m-d\TH:i:sP', strtotime($row['changed']))
            ],
            'links' => [
                'self' => [
                    'href' => $baseUrl . "/jsonapi/file/file/{$row['uuid']}"
                ]
            ]
        ];
    }

    private function formatResponse($row)
    {
        return $this->wrapDocument($this->formatData($row), false);
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
