<?php
namespace Lekhak\Modules\LekhakAuditTrail;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_audit_trail', '\Lekhak\Modules\LekhakAuditTrail\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_audit_trail_config (key TEXT, value TEXT)');
    }
}
