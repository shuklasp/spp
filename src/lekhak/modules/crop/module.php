<?php

namespace Lekhak\Modules\LekhakModuleCrop;

class LekhakModuleCrop {



    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by crop -->';
        } else {
            $build['#suffix'] = '<!-- Processed by crop -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'crop',
    'title' => 'Crop API',
    'instance' => new LekhakModuleCrop()
];
