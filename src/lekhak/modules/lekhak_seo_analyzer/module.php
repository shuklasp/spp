<?php
namespace Lekhak\Modules\LekhakModuleLekhakSeoAnalyzer;

class LekhakModuleLekhakSeoAnalyzer {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_seo_analyzer',
    'title' => 'LekhakSeoAnalyzer API',
    'instance' => new LekhakModuleLekhakSeoAnalyzer()
];
