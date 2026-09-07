<?php
/**
 * Translation Admin Services
 * Integrates with SPPLang to support scanning, listing and saving translated items.
 */

if (!function_exists('live_spplang_get')) {
    function live_spplang_get($la, $p) {
        $filters = [
            'locale' => $p['locale'] ?? null,
            'status' => $p['status'] ?? null,
            'search' => $p['search'] ?? null
        ];
        $translations = \SPPMod\SPPLang\SPPLang::getTranslations($filters);
        $la->setData(['translations' => $translations]);
    }
}

if (!function_exists('live_spplang_save')) {
    function live_spplang_save($la, $p) {
        $key = $p['key_code'] ?? '';
        $locale = $p['locale'] ?? '';
        $translation = $p['translation'] ?? '';
        $status = $p['status'] ?? 'active';

        if ($key === '' || $locale === '') {
            return $la->setStatus('error')->notify("Key and locale are required.");
        }

        \SPPMod\SPPLang\SPPLang::saveTranslation($key, $locale, $translation, $status);
        $la->notify("Translation saved successfully.");
    }
}

if (!function_exists('live_spplang_scan')) {
    function live_spplang_scan($la, $p) {
        $locale = $p['locale'] ?? 'en';
        $dir = dirname(SPP_BASE_DIR) . '/src';
        $newlyAdded = \SPPMod\SPPLang\SPPLang::scanDirectory($dir, $locale);
        
        $count = count($newlyAdded);
        $la->notify("Scan complete! Discovered {$count} new translation keys.", 'success');
        $la->setData(['new_keys' => $newlyAdded]);
    }
}
