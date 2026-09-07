<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\ImageStylesService;

class MediaStyleController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/media/style', method: 'GET')]
    public function serve()
    {
        $projectId = $_GET['project'] ?? '';
        $style = $_GET['style'] ?? 'thumbnail';
        $filename = $_GET['file'] ?? '';

        if (empty($projectId) || empty($filename) || !isset($this->config['projects'][$projectId])) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Not Found: Invalid project or filename.";
            exit;
        }

        $cleanStyle = preg_replace('/[^a-z0-9_-]/i', '', $style);
        $cleanFile = basename($filename);

        $path = ImageStylesService::generateDerivative($projectId, $cleanStyle, $cleanFile);

        if (!$path || !file_exists($path) || !is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Not Found: Media derivative could not be generated.";
            exit;
        }

        $etag = '"' . md5_file($path) . '"';
        $mtime = filemtime($path);
        $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

        $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        $ifModifiedSince = trim($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');

        if ($ifNoneMatch === $etag || $ifModifiedSince === $lastModified) {
            http_response_code(304);
            exit;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];

        $mime = $mimeMap[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . $cleanFile . '"');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $lastModified);
        header('Cache-Control: public, max-age=31536000, immutable');

        readfile($path);
        exit;
    }
}