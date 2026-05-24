<?php

namespace Lekhak\Modules\LekhakModuleSeoChecklist;

class LekhakModuleSeoChecklist {



    public function hook_page_meta_alter(&$meta, $context = []) {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            // Functional logic for SEO/Redirect family
            if (!isset($meta['tags'])) $meta['tags'] = [];
            $meta['tags'][] = '<!-- seo_checklist module active -->';
        } catch (\Exception $e) {}
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'seo_checklist',
    'title' => 'SEO Checklist',
    'instance' => new LekhakModuleSeoChecklist()
];
