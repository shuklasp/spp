<?php
namespace Lekhak\Modules\LekhakModuleLekhakRedirects;

class LekhakModuleLekhakRedirects {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_redirects',
    'title' => 'LekhakRedirects API',
    'instance' => new LekhakModuleLekhakRedirects()
];
