<?php
namespace Lekhak\Modules\LekhakModuleInlineEntityForm;

class LekhakModuleInlineEntityForm {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'inline_entity_form',
    'title' => 'InlineEntityForm API',
    'instance' => new LekhakModuleInlineEntityForm()
];
