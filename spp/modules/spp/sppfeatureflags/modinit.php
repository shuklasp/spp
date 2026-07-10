<?php

namespace SPPMod\SPPFeatureFlags;

if (!class_exists('\SPPMod\SPPFeatureFlags\FeatureManager')) {
    require_once __DIR__ . '/FeatureManager.php';
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPFeatureFlags\Commands\ToggleFeatureCommand')) {
        require_once __DIR__ . '/Commands/ToggleFeatureCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPFeatureFlags\Commands\ToggleFeatureCommand());
}
