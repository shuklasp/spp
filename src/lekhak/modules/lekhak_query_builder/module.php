<?php
namespace Lekhak\Modules\LekhakModuleLekhakQueryBuilder;

class LekhakModuleLekhakQueryBuilder {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_query_builder',
    'title' => 'LekhakQueryBuilder API',
    'instance' => new LekhakModuleLekhakQueryBuilder()
];
