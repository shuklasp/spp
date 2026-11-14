<?php
namespace Spp\Modules\Spp\SppDeploy;

class SppDeployModule extends \SPP\Module
{
    public function __construct()
    {
    }

    public function hook_init()
    {
        if (class_exists('\SPP\CLI\CommandManager')) {
            if (!class_exists('\SPPMod\SPPDeploy\Commands\DeployCanaryCommand')) {
                require_once __DIR__ . '/commands/DeployCanaryCommand.php';
            }
            \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeployCanaryCommand());

            if (!class_exists('\SPPMod\SPPDeploy\Commands\DeployMixedCommand')) {
                require_once __DIR__ . '/commands/DeployMixedCommand.php';
            }
            \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeployMixedCommand());

            if (!class_exists('\SPPMod\SPPDeploy\Commands\FleetClusterCommand')) {
                require_once __DIR__ . '/commands/FleetClusterCommand.php';
            }
            \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\FleetClusterCommand());

            if (!class_exists('\SPPMod\SPPDeploy\Commands\DeploySecretsCommand')) {
                require_once __DIR__ . '/commands/DeploySecretsCommand.php';
            }
            \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeploySecretsCommand());

            if (!class_exists('\SPPMod\SPPDeploy\Commands\DeployImpactCommand')) {
                require_once __DIR__ . '/commands/DeployImpactCommand.php';
            }
            \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeployImpactCommand());

            if (!class_exists('\SPPMod\SPPDeploy\Commands\DeploySentinelCommand')) {
                require_once __DIR__ . '/commands/DeploySentinelCommand.php';
            }
            \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeploySentinelCommand());

            if (!class_exists('\SPPMod\SPPDeploy\Commands\DeployTelemetryCommand')) {
                require_once __DIR__ . '/commands/DeployTelemetryCommand.php';
            }
            \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeployTelemetryCommand());

            if (!class_exists('\SPPMod\SPPDeploy\Commands\DeploySreHealCommand')) {
                require_once __DIR__ . '/commands/DeploySreHealCommand.php';
            }
            \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeploySreHealCommand());
        }
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

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPDeploy\Commands\DeployCanaryCommand')) {
        require_once __DIR__ . '/commands/DeployCanaryCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeployCanaryCommand());

    if (!class_exists('\SPPMod\SPPDeploy\Commands\DeployMixedCommand')) {
        require_once __DIR__ . '/commands/DeployMixedCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeployMixedCommand());

    if (!class_exists('\SPPMod\SPPDeploy\Commands\FleetClusterCommand')) {
        require_once __DIR__ . '/commands/FleetClusterCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\FleetClusterCommand());

    if (!class_exists('\SPPMod\SPPDeploy\Commands\DeploySecretsCommand')) {
        require_once __DIR__ . '/commands/DeploySecretsCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeploySecretsCommand());

    if (!class_exists('\SPPMod\SPPDeploy\Commands\DeployImpactCommand')) {
        require_once __DIR__ . '/commands/DeployImpactCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeployImpactCommand());

    if (!class_exists('\SPPMod\SPPDeploy\Commands\DeploySentinelCommand')) {
        require_once __DIR__ . '/commands/DeploySentinelCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeploySentinelCommand());

    if (!class_exists('\SPPMod\SPPDeploy\Commands\DeployTelemetryCommand')) {
        require_once __DIR__ . '/commands/DeployTelemetryCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeployTelemetryCommand());

    if (!class_exists('\SPPMod\SPPDeploy\Commands\DeploySreHealCommand')) {
        require_once __DIR__ . '/commands/DeploySreHealCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDeploy\Commands\DeploySreHealCommand());
}


return [
    'name' => 'sppdeploy',
    'instance' => new SppDeployModule()
];
