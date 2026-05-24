<?php

namespace Lekhak\Modules\LekhakModuleFocalPoint;

class LekhakModuleFocalPoint {



    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by focal_point -->';
        } else {
            $build['#suffix'] = '<!-- Processed by focal_point -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'focal_point',
    'title' => 'Focal Point',
    'instance' => new LekhakModuleFocalPoint()
];
