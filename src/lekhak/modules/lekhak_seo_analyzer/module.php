<?php

namespace Lekhak\Modules\LekhakModuleYoastSeo;

class LekhakModuleYoastSeo {



    public function hook_page_meta_alter(&$meta, $context = []) {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            // Functional logic for SEO/Redirect family
            if (!isset($meta['tags'])) $meta['tags'] = [];
            $meta['tags'][] = '<!-- yoast_seo module active -->';
        } catch (\Exception $e) {}
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_seo_analyzer',
    'title' => 'Real-time SEO',
    'instance' => new LekhakModuleYoastSeo()
];
