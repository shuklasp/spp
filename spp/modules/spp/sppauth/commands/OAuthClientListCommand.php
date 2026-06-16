<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

class OAuthClientListCommand extends Command
{
    protected string $name = 'oauth:client:list';
    protected string $description = 'List all OAuth 2.0 Client Apps';

    public function execute(array $args): void
    {
        $db = new SPPDB();
        $table = SPPDB::sppTable('oauth_clients');

        $clients = $db->execute_query("SELECT id, name, redirect_uri, created_at FROM $table ORDER BY created_at DESC");

        if (empty($clients)) {
            echo "No OAuth clients found.\n";
            return;
        }

        if (function_exists('printTable')) {
            printTable(['ID', 'Name', 'Redirect URI', 'Created At'], $clients);
        } else {
            foreach ($clients as $client) {
                echo "- [{$client['id']}] {$client['name']} ({$client['redirect_uri']})\n";
            }
        }
    }
}
