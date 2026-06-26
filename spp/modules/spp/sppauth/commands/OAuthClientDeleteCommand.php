<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

class OAuthClientDeleteCommand extends Command
{
    protected string $name = 'oauth:client:delete';
    protected string $description = 'Delete an OAuth 2.0 Client App';

    public function execute(array $args): void
    {
        $id = $args['id'] ?? null;
        if (!$id) {
            echo "Usage: php spp.php oauth:client:delete <id>\n";
            return;
        }

        $db = new SPPDB();
        $table = SPPDB::sppTable('oauth_clients');

        $db->execute_query("DELETE FROM $table WHERE id = ?", [$id]);

        // Also clean up associated tokens and codes
        $tokenTable = SPPDB::sppTable('oauth_tokens');
        $codeTable = SPPDB::sppTable('oauth_auth_codes');

        $db->execute_query("DELETE FROM $tokenTable WHERE client_id = ?", [$id]);
        $db->execute_query("DELETE FROM $codeTable WHERE client_id = ?", [$id]);

        echo "OAuth client '{$id}' and all associated tokens have been deleted.\n";
    }
}
