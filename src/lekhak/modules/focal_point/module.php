<?php
namespace Lekhak\Modules\LekhakModuleFocalPoint;

class LekhakModuleFocalPoint {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'focal_point',
    'title' => 'FocalPoint API',
    'instance' => new LekhakModuleFocalPoint()
];
