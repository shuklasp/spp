<?php

namespace SPPMod\SPPAI;

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPAI\Commands\RefactorEnterpriseCommand')) {
        require_once __DIR__ . '/Commands/RefactorEnterpriseCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPAI\Commands\RefactorEnterpriseCommand());

    if (!class_exists('\SPPMod\SPPAI\Commands\AiSreCommand')) {
        require_once __DIR__ . '/Commands/AiSreCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPAI\Commands\AiSreCommand());
}

