<?php

namespace SPPMod\SPPEbpf;

if (!class_exists('\SPPMod\SPPEbpf\EbpfProfiler')) {
    require_once __DIR__ . '/EbpfProfiler.php';
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPEbpf\Commands\AttachEbpfProfileCommand')) {
        require_once __DIR__ . '/Commands/AttachEbpfProfileCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPEbpf\Commands\AttachEbpfProfileCommand());
}
