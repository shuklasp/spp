<?php

namespace Lekhak\Modules\LekhakModuleRedirect;

class LekhakModuleRedirect {


    public function hook_request_init() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // Simulated lookup in redirect table
        /*
        $db = new \SPPMod\SPPDB\SPPDB();
        $redirect = $db->execute_query("SELECT redirect_url FROM redirects WHERE source_url=?", [$uri]);
        if ($redirect) { header("Location: ".$redirect[0]['redirect_url']); exit; }
        */
    }

    public function hook_page_meta_alter(&$meta, $context = []) {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            // Functional logic for SEO/Redirect family
            if (!isset($meta['tags'])) $meta['tags'] = [];
            $meta['tags'][] = '<!-- redirect module active -->';
        } catch (\Exception $e) {}
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_redirects',
    'title' => 'lekhak_redirects',
    'instance' => new LekhakModuleRedirect()
];
