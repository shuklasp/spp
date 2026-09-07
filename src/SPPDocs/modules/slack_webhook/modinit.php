<?php

namespace AppMod\SPPDocs\SlackWebhook;

class SlackWebhookModule
{
    private static array $auditLog = [];

    /**
     * Hook fired whenever a document is saved.
     */
    public function hook_document_save($documentData)
    {
        $title = $documentData['title'] ?? ($documentData['filename'] ?? 'Untitled');
        $project = $documentData['project_id'] ?? 'spp';
        
        $entry = [
            'time' => time(),
            'project' => $project,
            'title' => $title,
            'status' => 'dispatched',
        ];
        
        self::$auditLog[] = $entry;
        return $entry;
    }

    public static function getAuditLog(): array
    {
        return self::$auditLog;
    }
}

$instance = new SlackWebhookModule();
\App\SPPDocs\Services\ModuleManager::registerModuleInstance('spp', 'slack_webhook', $instance);
return $instance;