<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;

class UploadController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf', 'text/plain', 'text/csv',
        'application/zip', 'application/x-tar', 'application/gzip',
        'application/json', 'application/xml',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/uploads/store', method: 'POST')]
    public function store()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $issueId = $_POST['issue_id'] ?? '';
        $redirect = $_POST['redirect'] ?? '';

        if (!$projectId || !$issueId) exit('Invalid input');

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $user = $this->getProjectUser();
        if (!$user) exit('Access denied');

        $issuesDir = $this->getIssuesDir($project);
        $issue = $this->loadIssue($issuesDir, $issueId);
        if (!$issue) exit('Issue not found');

        if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            exit('No file uploaded or upload error');
        }

        $file = $_FILES['attachment'];

        // Validate size
        if ($file['size'] > self::MAX_SIZE) exit('File too large. Maximum 10MB.');

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, self::ALLOWED_TYPES)) exit('File type not allowed: ' . $mime);

        // Create uploads directory
        $uploadsDir = $issuesDir . '/uploads';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

        // Generate safe filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        $storedName = time() . '_' . bin2hex(random_bytes(4)) . '_' . preg_replace('/[^a-zA-Z0-9_\-.]/', '_', basename($file['name']));
        $destPath = $uploadsDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            exit('Failed to save file');
        }

        // Add attachment to issue
        if (!isset($issue['attachments'])) $issue['attachments'] = [];
        $issue['attachments'][] = [
            'filename' => $file['name'],
            'stored_name' => $storedName,
            'mime' => $mime,
            'size' => $file['size'],
            'uploaded_by' => $user['username'],
            'uploaded_at' => time(),
        ];

        $issue['comments'][] = [
            'author' => 'System', 'type' => 'event', 'timestamp' => time(),
            'content' => $user['username'] . " attached \"{$file['name']}\".",
        ];
        $issue['updated_at'] = time();
        unset($issue['id']);
        $this->saveIssue($issuesDir, $issueId, $issue);
        $this->logActivity($issuesDir, 'issue.attachment', $user['username'], $issueId, "Attached \"{$file['name']}\" to \"{$issue['title']}\"");

        $url = $redirect ?: \SPP\App::url('issues/view') . "?projectId=$projectId&issueId=$issueId";
        \SPP\Response::redirect($url);
    }

    #[Route('/uploads/serve', method: 'GET')]
    public function serve()
    {
        $this->ensureSession();
        $projectId = $_GET['projectId'] ?? $_GET['project'] ?? '';
        $file = $_GET['file'] ?? '';

        if (!$projectId || !$file) {
            http_response_code(400);
            exit('Invalid request');
        }

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) {
            http_response_code(404);
            exit('Project not found');
        }

        $user = $this->getProjectUser();
        if (!$user && empty($project['public_read'])) {
            http_response_code(403);
            exit('Access denied: Authentication required to view project attachments.');
        }

        $issuesDir = $this->getIssuesDir($project);
        $cleanFile = basename($file);
        $filePath = $issuesDir . '/uploads/' . $cleanFile;

        if (!file_exists($filePath)) {
            http_response_code(404);
            exit('File not found');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $ext = strtolower(pathinfo($cleanFile, PATHINFO_EXTENSION));
        $isSvg = ($mime === 'image/svg+xml' || $ext === 'svg');

        // Security fortification: SVGs and unverified binaries must NEVER execute inline on application origin
        if ($isSvg) {
            header("Content-Security-Policy: default-src 'none'; sandbox;");
            header('Content-Disposition: attachment; filename="' . $cleanFile . '"');
        } else {
            $safeInline = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']);
            $disp = $safeInline ? 'inline' : 'attachment';
            header('Content-Disposition: ' . $disp . '; filename="' . $cleanFile . '"');
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile($filePath);
        exit;
    }
}
