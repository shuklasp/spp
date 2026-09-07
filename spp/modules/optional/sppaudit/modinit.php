<?php

namespace SPPMod\SPPAudit;

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPAudit\Commands\AuditSecurityCapabilitiesCommand')) {
        require_once __DIR__ . '/Commands/AuditSecurityCapabilitiesCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPAudit\Commands\AuditSecurityCapabilitiesCommand());
}
