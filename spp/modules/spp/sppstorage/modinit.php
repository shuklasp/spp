<?php

namespace SPPMod\SPPStorage;

if (!class_exists('\SPPMod\SPPStorage\CrdtSyncEngine')) {
    require_once __DIR__ . '/CrdtSyncEngine.php';
}

if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
    require_once __DIR__ . '/src/FilePropsManager.php';
}

if (!class_exists('\SPPMod\SPPStorage\FileIntegritySigner')) {
    require_once __DIR__ . '/src/FileIntegritySigner.php';
}

if (!class_exists('\SPPMod\SPPStorage\KnowledgeGraphEngine')) {
    require_once __DIR__ . '/src/KnowledgeGraphEngine.php';
}

if (!class_exists('\SPPMod\SPPStorage\PolyglotProxyGenerator')) {
    require_once __DIR__ . '/src/PolyglotProxyGenerator.php';
}

if (!class_exists('\SPPMod\SPPStorage\ComponentCatalogService')) {
    require_once __DIR__ . '/src/ComponentCatalogService.php';
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPStorage\Commands\CrdtSyncCommand')) {
        require_once __DIR__ . '/Commands/CrdtSyncCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPStorage\Commands\CrdtSyncCommand());
}
