<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployInitCommand extends Command
{
    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $baseDir = SPP_BASE_DIR; // Should be spp/
        $rootDir = dirname($baseDir); // Should be school1/

        echo "🚀 Initializing SPPDeploy Configuration...\n";

        $ignoreFile = $rootDir . '/.sppignore';
        if (!file_exists($ignoreFile)) {
            $ignoreContent = <<<IGNORE
# SPPDeploy Ignore File
# Paths listed here will not be synchronized to the remote server

/.git
/spp/var/cache
/spp/var/logs
/spp/var/sessions
/spp/var/backups
/.sppdeploy.yml
/.maintenance
IGNORE;
            file_put_contents($ignoreFile, $ignoreContent);
            echo "✅ Created .sppignore\n";
        } else {
            echo "ℹ️  .sppignore already exists, skipping.\n";
        }

        $ymlFile = $rootDir . '/.sppdeploy.yml';
        if (!file_exists($ymlFile)) {
            echo "\n❓ Enter a name for your primary environment (e.g., 'production' or 'staging') [production]: ";
            $handle = fopen("php://stdin", "r");
            $envName = trim(fgets($handle));
            fclose($handle);
            if (empty($envName)) {
                $envName = 'production';
            }

            $token = bin2hex(random_bytes(32)); // 64-char hex string

            $ymlContent = <<<YML
# SPPDeploy Configuration File

default_environment: {$envName}

environments:
  {$envName}:
    url: http://your-remote-server.com
    token: {$token}
#  staging:
#    url: http://staging.your-remote-server.com
#    token: some_secure_token_here
#  db_node:
#    url: http://10.0.0.51
#    token: internal_db_token

# clusters:
#   web_farm:
#     - {$envName}
#     - http://second-node.your-remote-server.com

# webhooks:
#   - "https://discord.com/api/webhooks/.../..."
#   - "https://hooks.slack.com/services/.../.../..."

# post_deploy:
#   - "composer install --no-dev"
#   - "php spp.php cache:clear"
#   # Hub-and-Spoke Gateway Orchestration: Distribute specialized tasks to internal sub-servers
#   # - "php spp.php deploy:push db_node --key=internal_db_token"
#   # - "php spp.php deploy:run http://10.0.0.52 'queue:restart' --key=queue_token"

# anonymize:
#   users:
#     - password
#     - email
YML;
            file_put_contents($ymlFile, $ymlContent);
            echo "✅ Created .sppdeploy.yml\n";
            echo "\n🔑 Generated a secure deployment token for your {$envName} environment:\n";
            echo "   -> {$token}\n";
            echo "   Make sure to set this token in your remote server's SPP environment variable (SPP_DEPLOY_TOKEN).\n";
        } else {
            echo "ℹ️  .sppdeploy.yml already exists, skipping.\n";
        }

        echo "\n🎉 Initialization complete. You are ready to deploy!\n";
    }

    public function getName(): string
    {
        return 'deploy:init';
    }
}
