<?php
namespace Lekhak\Modules\LekhakModuleLekhakSitemap;

class LekhakModuleLekhakSitemap {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_sitemap',
    'title' => 'LekhakSitemap API',
    'instance' => new LekhakModuleLekhakSitemap()
];
