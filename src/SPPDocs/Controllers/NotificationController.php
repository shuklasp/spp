<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;

class NotificationController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/notifications', method: 'GET')]
    public function index()
    {
        $projectId = $_GET['projectId'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        $project = $this->config['projects'][$projectId];
        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();
        $user = $this->getProjectUser();
        if (!$user) return $this->render('errors/404', ['message' => 'Please log in']);

        $notifications = $this->loadNotifications($issuesDir, $user['username']);

        return $this->render('notifications/index', [
            'project' => $project, 'project_id' => $projectId,
            'notifications' => $notifications,
            'user' => $user, 'csrf_token' => $_SESSION['sppdocs_csrf'],
        ]);
    }

    #[Route('/notifications/read', method: 'POST')]
    public function markRead()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $notifId = $_POST['notification_id'] ?? '';

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $user = $this->getProjectUser();
        if (!$user) exit('Access denied');

        $issuesDir = $this->getIssuesDir($project);
        $notifications = $this->loadNotifications($issuesDir, $user['username']);

        foreach ($notifications as &$n) {
            if (($n['id'] ?? '') === $notifId) {
                $n['read'] = true;
                break;
            }
        }
        unset($n);
        $this->saveNotifications($issuesDir, $user['username'], $notifications);

        header("Location: " . \SPP\App::getBaseUrl() . "/notifications?projectId=$projectId");
        exit;
    }

    #[Route('/notifications/read-all', method: 'POST')]
    public function markAllRead()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $user = $this->getProjectUser();
        if (!$user) exit('Access denied');

        $issuesDir = $this->getIssuesDir($project);
        $notifications = $this->loadNotifications($issuesDir, $user['username']);

        foreach ($notifications as &$n) $n['read'] = true;
        unset($n);
        $this->saveNotifications($issuesDir, $user['username'], $notifications);

        header("Location: " . \SPP\App::getBaseUrl() . "/notifications?projectId=$projectId");
        exit;
    }

    /**
     * Static helper: create a notification for a user.
     */
    public static function notify(string $issuesDir, string $username, string $type, string $message, string $link, ?string $emailTo = null): void
    {
        $dir = $issuesDir . '/notifications';
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $file = $dir . '/' . basename($username) . '.json';
        $notifications = [];
        if (file_exists($file)) {
            $notifications = json_decode(file_get_contents($file), true) ?: [];
        }

        $notif = [
            'id' => 'n_' . time() . '_' . bin2hex(random_bytes(3)),
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'read' => false,
            'timestamp' => time(),
        ];
        array_unshift($notifications, $notif);

        // Cap at 200 notifications
        $notifications = array_slice($notifications, 0, 200);
        file_put_contents($file, json_encode($notifications, JSON_PRETTY_PRINT));

        // Send email notification if configured
        if ($emailTo) {
            self::sendEmail($emailTo, $type, $message, $link);
        }
    }

    /**
     * Get unread count for a user (used by the header partial).
     */
    public static function getUnreadCount(string $issuesDir, string $username): int
    {
        $file = $issuesDir . '/notifications/' . basename($username) . '.json';
        if (!file_exists($file)) return 0;
        $notifications = json_decode(file_get_contents($file), true) ?: [];
        return count(array_filter($notifications, fn($n) => empty($n['read'])));
    }

    private function loadNotifications(string $issuesDir, string $username): array
    {
        $file = $issuesDir . '/notifications/' . basename($username) . '.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    private function saveNotifications(string $issuesDir, string $username, array $notifications): void
    {
        $dir = $issuesDir . '/notifications';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $file = $dir . '/' . basename($username) . '.json';
        file_put_contents($file, json_encode($notifications, JSON_PRETTY_PRINT));
    }

    /**
     * Send an email notification using PHP's built-in mail() function.
     */
    private static function sendEmail(string $to, string $type, string $message, string $link): void
    {
        $subject = '[SPPDocs] ' . ucfirst(str_replace('.', ' - ', $type));
        $headers = "From: noreply@sppdocs.local\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $body = "
        <div style='font-family: -apple-system, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px;'>
            <div style='padding: 16px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #3b82f6;'>
                <p style='margin: 0 0 8px 0; font-weight: 600;'>{$message}</p>
                <a href='{$link}' style='color: #3b82f6; text-decoration: none;'>View in SPPDocs →</a>
            </div>
            <p style='font-size: 12px; color: #94a3b8; margin-top: 16px;'>You received this because of your notification preferences.</p>
        </div>";

        @mail($to, $subject, $body, $headers);
    }
}
