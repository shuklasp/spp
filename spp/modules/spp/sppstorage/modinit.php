<?php

namespace SPPMod\SPPStorage;

if (!class_exists('\SPPMod\SPPStorage\CrdtSyncEngine')) {
    require_once __DIR__ . '/CrdtSyncEngine.php';
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPStorage\Commands\CrdtSyncCommand')) {
        require_once __DIR__ . '/Commands/CrdtSyncCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPStorage\Commands\CrdtSyncCommand());
}
