<?php
namespace Lekhak\Modules\LekhakModuleDropzonejs;

class LekhakModuleDropzonejs {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'dropzonejs',
    'title' => 'Dropzonejs API',
    'instance' => new LekhakModuleDropzonejs()
];
