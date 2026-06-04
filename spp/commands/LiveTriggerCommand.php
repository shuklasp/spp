<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class LiveTriggerCommand extends Command
{
    protected string $name = 'live:trigger';
    protected string $description = 'Push a live event to clients';

    public function execute(array $args): void
    {
        $channel = null;
        $event = null;
        $payload = null;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--channel=')) $channel = substr($arg, 10);
            elseif (str_starts_with($arg, '--event=')) $event = substr($arg, 8);
            elseif (str_starts_with($arg, '--payload=')) $payload = substr($arg, 10);
        }

        if (!$channel || !$event) {
            echo "Usage: php spp.php live:trigger --channel=<channel> --event=<event> [--payload=<json>]\n";
            return;
        }

        echo "Triggering event '$event' on channel '$channel'...\n";
        if (class_exists('\\SPPMod\\SPPLive\\SPPLive')) {
            $data = $payload ? json_decode($payload, true) : [];
            if (method_exists('\\SPPMod\\SPPLive\\SPPLive', 'broadcast')) {
                \SPPMod\SPPLive\SPPLive::broadcast($channel, $event, $data);
                echo "Event broadcasted successfully.\n";
            } else {
                echo "Broadcast method not found. This is a stub.\n";
            }
        } else {
            echo "SPPLive module not active.\n";
        }
    }
}
