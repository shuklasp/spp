<?php

namespace Lekhak\Modules\LekhakModuleEntityReferenceRevisions;

class LekhakModuleEntityReferenceRevisions {



    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by entity_reference_revisions -->';
        } else {
            $build['#suffix'] = '<!-- Processed by entity_reference_revisions -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'entity_reference_revisions',
    'title' => 'Entity Reference Revisions',
    'instance' => new LekhakModuleEntityReferenceRevisions()
];
