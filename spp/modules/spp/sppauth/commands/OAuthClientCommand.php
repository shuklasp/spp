<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

class OAuthClientCommand extends Command
{
    protected string $name = 'oauth:client:create';
    protected string $description = 'Create a new OAuth 2.0 Client App';

    public function execute(array $args): void
    {
        $name = $args['name'] ?? null;
        $redirect_uri = $args['redirect_uri'] ?? null;

        if (!$name || !$redirect_uri) {
            echo "Usage: php spp.php oauth:client:create <name> <redirect_uri>\n";
            return;
        }

        $db = new SPPDB();
        $table = SPPDB::sppTable('oauth_clients');

        $clientId = 'client_' . bin2hex(random_bytes(4));
        $clientSecret = bin2hex(random_bytes(16));

        $db->execute_query("INSERT INTO $table (id, secret, name, redirect_uri) VALUES (?, ?, ?, ?)", [$clientId, $clientSecret, $name, $redirect_uri]);

        echo "\nOAuth Client Created Successfully!\n";
        echo "---------------------------------\n";
        echo "Client ID:     $clientId\n";
        echo "Client Secret: $clientSecret\n";
        echo "App Name:      $name\n";
        echo "Redirect URI:  $redirect_uri\n";
        echo "---------------------------------\n";
    }
}
