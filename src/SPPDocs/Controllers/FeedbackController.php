<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use SPP\Response;

class FeedbackController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/api/feedback/submit', method: 'POST')]
    public function submit()
    {
        // Accept both form POST and JSON body
        $input = $_POST;
        if (empty($input)) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        $projectId = trim($input['project_id'] ?? $input['project'] ?? '');
        $path = trim($input['path'] ?? 'unknown');
        $rating = strtolower(trim($input['rating'] ?? ''));
        $comment = trim($input['comment'] ?? '');

        if (empty($projectId) || !in_array($rating, ['yes', 'no'], true)) {
            Response::json([
                'success' => false,
                'message' => 'Invalid parameters. Project and rating (yes/no) are required.'
            ], 400);
            return;
        }

        $dataDir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }

        $file = $dataDir . '/feedback.json';
        $feedbackList = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

        $record = [
            'id' => 'fb_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'project_id' => $projectId,
            'path' => $path,
            'rating' => $rating,
            'comment' => $comment,
            'created_at' => time(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 150)
        ];

        $feedbackList[] = $record;

        // Keep last 1000 feedback entries
        if (count($feedbackList) > 1000) {
            $feedbackList = array_slice($feedbackList, -1000);
        }

        file_put_contents($file, json_encode($feedbackList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        Response::json([
            'success' => true,
            'message' => 'Thank you for your feedback!'
        ]);
    }
}