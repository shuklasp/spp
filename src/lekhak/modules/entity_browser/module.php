<?php

namespace Lekhak\Modules\LekhakModuleEntityBrowser;

class LekhakModuleEntityBrowser {



    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by entity_browser -->';
        } else {
            $build['#suffix'] = '<!-- Processed by entity_browser -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'entity_browser',
    'title' => 'Entity Browser',
    'instance' => new LekhakModuleEntityBrowser()
];
