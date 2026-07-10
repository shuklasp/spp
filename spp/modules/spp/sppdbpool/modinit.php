<?php

namespace SPPMod\SPPDbPool;

if (!class_exists('\SPPMod\SPPDbPool\ConnectionPooler')) {
    require_once __DIR__ . '/ConnectionPooler.php';
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPDbPool\Commands\OrchestrateDbPoolCommand')) {
        require_once __DIR__ . '/Commands/OrchestrateDbPoolCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDbPool\Commands\OrchestrateDbPoolCommand());
}
