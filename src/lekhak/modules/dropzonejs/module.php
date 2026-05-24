<?php

namespace Lekhak\Modules\LekhakModuleDropzonejs;

class LekhakModuleDropzonejs {



    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by dropzonejs -->';
        } else {
            $build['#suffix'] = '<!-- Processed by dropzonejs -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'dropzonejs',
    'title' => 'DropzoneJS',
    'instance' => new LekhakModuleDropzonejs()
];
