<?php

namespace SPPMod\SPPChaos;

if (!class_exists('\SPPMod\SPPChaos\ChaosMonkey')) {
    require_once __DIR__ . '/ChaosMonkey.php';
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPChaos\Commands\InjectChaosCommand')) {
        require_once __DIR__ . '/Commands/InjectChaosCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPChaos\Commands\InjectChaosCommand());
}
