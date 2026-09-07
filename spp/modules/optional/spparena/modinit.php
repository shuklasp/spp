<?php

namespace SPPMod\SPPArena;

if (!class_exists('\SPPMod\SPPArena\MemoryArena')) {
    require_once __DIR__ . '/MemoryArena.php';
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPArena\Commands\MonitorArenaMemoryCommand')) {
        require_once __DIR__ . '/Commands/MonitorArenaMemoryCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPArena\Commands\MonitorArenaMemoryCommand());
}
