<?php
namespace Lekhak\Modules\LekhakModuleSeoChecklist;

class LekhakModuleSeoChecklist {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'seo_checklist',
    'title' => 'SeoChecklist API',
    'instance' => new LekhakModuleSeoChecklist()
];
