<?php

namespace Lekhak\Modules\LekhakModuleFieldGroup;

class LekhakModuleFieldGroup {



    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by field_group -->';
        } else {
            $build['#suffix'] = '<!-- Processed by field_group -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'field_group',
    'title' => 'Field Group',
    'instance' => new LekhakModuleFieldGroup()
];
