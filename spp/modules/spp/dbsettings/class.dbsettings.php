<?php
namespace SPPMod\DBSettings;

/**
 * class DBSettings
 * 
 * Provides database persistence for settings via the spp_config table.
 */
class DBSettings extends \SPP\SPPObject
{
    /**
     * Retrieves a setting from the database.
     */
    public static function get(string $key, string $appname): mixed
    {
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('config');
            
            // Check for app-specific entry first, then global
            $query = "SELECT propval FROM {$table} WHERE (appname = ? OR appname IS NULL OR appname = '') AND propname = ? ORDER BY appname DESC LIMIT 1";
            $result = $db->execute_query($query, [$appname, $key]);
            
            if (!empty($result)) {
                $val = $result[0]['propval'];
                // Try to unserialize or decode if needed, but for now assume string/json
                $decoded = json_decode($val, true);
                return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
            }
        } catch (\Throwable $e) {
            // Silently fail if DB is not ready or table doesn't exist
        }
        return null;
    }

    /**
     * Persists a setting to the database.
     */
    public static function set(string $key, mixed $value, string $appname): void
    {
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('config');
            
            $val = is_scalar($value) ? (string)$value : json_encode($value);
            
            // Upsert logic
            $checkQuery = "SELECT id FROM {$table} WHERE appname = ? AND propname = ?";
            $exists = $db->execute_query($checkQuery, [$appname, $key]);
            
            if (!empty($exists)) {
                $updateQuery = "UPDATE {$table} SET propval = ? WHERE appname = ? AND propname = ?";
                $db->execute_query($updateQuery, [$val, $appname, $key]);
            } else {
                $insertQuery = "INSERT INTO {$table} (appname, propname, propval) VALUES (?, ?, ?)";
                $db->execute_query($insertQuery, [$appname, $key, $val]);
            }
        } catch (\Throwable $e) {
            // Log error or throw if critical
        }
    }
}
