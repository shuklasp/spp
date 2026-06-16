<?php

namespace SPPMod\SPPAuth;

use SPPMod\SPPDB\SPPDB;

class AuditLogger
{
    public static function log(string $eventType, ?string $userId = null, ?string $targetId = null, $details = null)
    {
        $db = new SPPDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $detailsJson = is_array($details) || is_object($details) ? json_encode($details) : $details;

        try {
            $db->insertValues(SPPDB::sppTable('sppauth_audit_log'), [
                'event_type' => $eventType,
                'user_id' => $userId,
                'target_id' => $targetId,
                'details' => $detailsJson,
                'ip_address' => $ip
            ]);
        } catch (\Exception $e) {
            // Failsafe: Do not crash the application if audit log fails, 
            // but ideally log to a file.
            error_log("Failed to insert into sppauth_audit_log: " . $e->getMessage());
        }
    }
}
