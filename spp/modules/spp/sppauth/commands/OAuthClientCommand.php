<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

class OAuthClientCommand extends Command
{
    protected string $name = 'oauth:client:create';
    protected string $description = 'Create a new OAuth 2.0 Client App';

    public function renderAdminUI(): string
    {
        $html = '<div class="command-ui-container">';
        $html .= '  <h3>Create OAuth 2.0 Client</h3>';
        $html .= '  <p>Fill out the details below to provision a new OAuth client application.</p>';
        $html .= '  <div class="form-group" style="margin-bottom: 15px;">';
        $html .= '    <label style="display:block; margin-bottom:5px;">Application Name</label>';
        $html .= '    <input type="text" id="oauthName" class="spp-input" placeholder="e.g. Mobile App" style="width:100%; background:var(--bg-color-alt); color:var(--text); border:1px solid var(--border-color); padding: 8px; border-radius: 4px;">';
        $html .= '  </div>';
        $html .= '  <div class="form-group" style="margin-bottom: 15px;">';
        $html .= '    <label style="display:block; margin-bottom:5px;">Redirect URI</label>';
        $html .= '    <input type="text" id="oauthUri" class="spp-input" placeholder="e.g. https://app.example.com/callback" style="width:100%; background:var(--bg-color-alt); color:var(--text); border:1px solid var(--border-color); padding: 8px; border-radius: 4px;">';
        $html .= '  </div>';
        $html .= '  <button class="spp-btn primary-btn" onclick="let n = document.getElementById(\'oauthName\').value; let u = document.getElementById(\'oauthUri\').value; if(n && u) executeCommand(\'oauth:client:create\', \'--name=\"\' + n + \'\" --redirect_uri=\"\' + u + \'\"\');">Create Client</button>';
        $html .= '</div>';
        return $html;
    }

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $name = $args['name'] ?? null;
        $redirect_uri = $args['redirect_uri'] ?? null;

        if (!$name || !$redirect_uri) {
            echo "Usage: php spp.php oauth:client:create --name=\"<name>\" --redirect_uri=\"<redirect_uri>\"\n";
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
