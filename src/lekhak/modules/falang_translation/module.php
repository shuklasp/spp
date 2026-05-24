<?php
namespace Lekhak\Modules\FalangTranslation;

/**
 * Provides multi-language support and content translation management across the platform.
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('falang_translation', '\Lekhak\Modules\FalangTranslation\Module');
    }

    public static function hook_entity_load_alter(&$entity) {
        // Falang: On-the-fly translation overriding
        global $current_lang;
        if (isset($current_lang) && $current_lang !== "en") {
            $db = new \SPPMod\SPPDB\SPPDB();
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_falang_translations (entity_id INTEGER, lang TEXT, field TEXT, translation TEXT)");
            // Override entity fields with translated strings if available
        }
    }

}
