<?php

namespace Lekhak\Modules\LekhakModuleXmlsitemap;

class LekhakModuleXmlsitemap {



    public function hook_page_meta_alter(&$meta, $context = []) {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            // Functional logic for SEO/Redirect family
            if (!isset($meta['tags'])) $meta['tags'] = [];
            $meta['tags'][] = '<!-- xmlsitemap module active -->';
        } catch (\Exception $e) {}
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_sitemap',
    'title' => 'XML sitemap',
    'instance' => new LekhakModuleXmlsitemap()
];
