<?php

namespace SPPMod\SPPCrypto;

if (!class_exists('\SPPMod\SPPCrypto\MpcKeySharder')) {
    require_once __DIR__ . '/MpcKeySharder.php';
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPCrypto\Commands\GenerateCryptoShardCommand')) {
        require_once __DIR__ . '/Commands/GenerateCryptoShardCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPCrypto\Commands\GenerateCryptoShardCommand());
}
