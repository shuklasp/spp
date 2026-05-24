<?php

namespace Lekhak\Modules\LekhakModuleInlineEntityForm;

class LekhakModuleInlineEntityForm {



    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by inline_entity_form -->';
        } else {
            $build['#suffix'] = '<!-- Processed by inline_entity_form -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'inline_entity_form',
    'title' => 'Inline Entity Form',
    'instance' => new LekhakModuleInlineEntityForm()
];
