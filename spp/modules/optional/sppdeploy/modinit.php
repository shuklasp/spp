<?php
namespace Spp\Modules\Spp\SppDeploy;

class SppDeployModule extends \SPP\Module
{
    public function __construct()
    {
    }

    public function hook_init()
    {
    }

    public function hook_request_init()
    {
        // This acts as a fallback for standard SPP environments where index.php isn't patched.
        if (class_exists('\SPPMod\SPPDeploy\SPPDeploy') && \SPPMod\SPPDeploy\SPPDeploy::isDeployRequest()) {
            \SPPMod\SPPDeploy\SPPDeploy::handle();
            exit;
        }
    }
}

return [
    'name' => 'sppdeploy',
    'instance' => new SppDeployModule()
];
