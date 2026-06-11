<?php
namespace Lekhak\Modules\LekhakModuleEntityBrowser;

class LekhakModuleEntityBrowser {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'entity_browser',
    'title' => 'EntityBrowser API',
    'instance' => new LekhakModuleEntityBrowser()
];
