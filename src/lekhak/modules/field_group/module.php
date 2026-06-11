<?php
namespace Lekhak\Modules\LekhakModuleFieldGroup;

class LekhakModuleFieldGroup {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'field_group',
    'title' => 'FieldGroup API',
    'instance' => new LekhakModuleFieldGroup()
];
