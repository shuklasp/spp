<?php

namespace Spp\Modules\Spp\SppMigrate;

class SppMigrateModule
{
    public function __construct()
    {
        // Module initialized
    }

    public function hook_init()
    {
        // Initialization tasks
    }

    public function hook_request_init()
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        // --- Server API Endpoints ---
        if (preg_match('#/api/sppmigrate/(ping|diff|deploy|deploy/files|deploy/db)/?$#', $path)) {
            \SPPMod\SPPMigrate\Api\Receiver::handle($path);
        }

        // --- Client UI Endpoints ---
        if (preg_match('#/sppadmin/migrate/?$#', $path)) {
            require_once __DIR__ . '/ui/dashboard.php';
            exit;
        }

        // Lekhak extension wrapper
        if (preg_match('#/lekhak/admin/migrate/?$#', $path)) {
            $_GET['context'] = 'lekhak';
            require_once __DIR__ . '/ui/dashboard.php';
            exit;
        }
    }
}

return [
    'name' => 'sppmigrate',
    'instance' => new SppMigrateModule()
];
