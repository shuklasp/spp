<?php

$sppdb_version = '1.0.0';
$sppdb_events[] = 'sppdb_connection';   //class.sppdb.php
SPP\SPPEvent::registerEvents($sppdb_events);

if (class_exists('\\SPP\\DB')) {
    \SPP\DB::setProvider(new \SPPMod\SPPDB\SPPDB());
}
if (class_exists('\\SPP\\DB\\Sequence')) {
    \SPP\DB\Sequence::setProvider('\\SPPMod\\SPPDB\\SPPSequence');
}

if (class_exists('\\SPP\\SPPEvent')) {
    \SPP\SPPEvent::listen('api.resolve_base_entity', function (\SPP\EventParams $params) {
        if (class_exists('\\SPPMod\\SPPEntity\\SPPEntity')) {
            $params->set('base_class', '\\SPPMod\\SPPEntity\\SPPEntity');
        }
    });
}

if (class_exists('\\SPP\\CLI\\CommandManager')) {
    if (!class_exists('\\SPPMod\\SPPDB\\Commands\\VerifyZeroDowntimeCommand')) {
        require_once __DIR__ . '/commands/VerifyZeroDowntimeCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDB\Commands\VerifyZeroDowntimeCommand());
}
