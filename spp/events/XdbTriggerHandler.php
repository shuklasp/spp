<?php
namespace EventHandlers;

use SPP\EventHandler;

/**
 * class XdbTriggerHandler
 * 
 * Example implementation of database triggers using the SPP Event system.
 * This handler listens to XDB mutation events and enforces business rules.
 */
class XdbTriggerHandler extends EventHandler {
    
    /**
     * Map events to handler methods.
     */
    public static function getSubscribedEvents(): array {
        return [
            'xdb.after_insert'  => 'onAfterInsert',
            'xdb.before_delete' => 'onBeforeDelete',
            'xdb.before_update' => 'onBeforeUpdate'
        ];
    }

    /**
     * React after a record is inserted.
     */
    public function onAfterInsert(&$params) {
        $table = $params['table'];
        $id = $params['id'];
        
        // Example logic: Multi-table synchronization or notification
        // error_log("SPP XDB TRIGGER [AfterInsert]: Table=$table, ID=$id");
    }

    /**
     * Enforce data integrity before deletion.
     */
    public function onBeforeDelete(&$params) {
        $table = $params['table'];
        $where = $params['where'];
        
        // Prevent accidental deletion of critical system tables
        if ($table === 'system_settings' || $table === 'core_modules') {
            throw new \Exception("XDB Security Violation: Mutation of protected table '$table' is restricted.");
        }
    }

    /**
     * Transform data before it hits the disk.
     */
    public function onBeforeUpdate(&$params) {
        $table = $params['table'];
        
        // Example: Auto-timestamping an 'updated_at' column if it exists
        if (isset($params['data'])) {
            $params['data']['last_modified'] = date('Y-m-d H:i:s');
        }
    }
}
