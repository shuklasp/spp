<?php
namespace Lekhak\Modules\LekhakModuleMediaLibrary;

class LekhakModuleMediaLibrary {
    public function hook_entity_view_alter(&$build, $context = []) {
        // Valid hook stub
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'media_library',
    'title' => 'MediaLibrary API',
    'instance' => new LekhakModuleMediaLibrary()
];
