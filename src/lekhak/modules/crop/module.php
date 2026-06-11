<?php
namespace Lekhak\Modules\LekhakModuleCrop;

class LekhakModuleCrop {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'crop',
    'title' => 'Crop API',
    'instance' => new LekhakModuleCrop()
];
