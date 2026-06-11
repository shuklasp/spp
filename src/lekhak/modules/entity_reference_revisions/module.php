<?php
namespace Lekhak\Modules\LekhakModuleEntityReferenceRevisions;

class LekhakModuleEntityReferenceRevisions {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'entity_reference_revisions',
    'title' => 'EntityReferenceRevisions API',
    'instance' => new LekhakModuleEntityReferenceRevisions()
];
