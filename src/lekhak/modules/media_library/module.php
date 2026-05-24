<?php

namespace Lekhak\Modules\LekhakModuleMediaLibrary;

class LekhakModuleMediaLibrary {



    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by media_library -->';
        } else {
            $build['#suffix'] = '<!-- Processed by media_library -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'media_library',
    'title' => 'Media Library',
    'instance' => new LekhakModuleMediaLibrary()
];
