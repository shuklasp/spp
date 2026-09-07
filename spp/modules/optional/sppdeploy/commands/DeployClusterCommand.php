<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployClusterCommand extends Command
{
    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $clusterName = $args[2] ?? null;
        if (!$clusterName) {
            echo "Error: Cluster name required.\n";
            echo "Usage: php spp.php deploy:cluster <cluster_name>\n";
            return;
        }

        $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
        if (!file_exists($confFile)) {
            echo "Error: .sppdeploy.yml configuration file not found.\n";
            return;
        }

        $conf = @yaml_parse_file($confFile);
        if (!isset($conf['clusters'][$clusterName]) || !is_array($conf['clusters'][$clusterName])) {
            echo "Error: Cluster '{$clusterName}' not found or invalid in .sppdeploy.yml.\n";
            return;
        }

        $nodes = $conf['clusters'][$clusterName];
        if (empty($nodes)) {
            echo "Error: Cluster '{$clusterName}' has no nodes configured.\n";
            return;
        }

        echo "\n🚀 Initiating cluster deployment to " . count($nodes) . " nodes in '{$clusterName}'...\n";
        echo str_repeat("=", 50) . "\n";

        // To reuse the DeployPushCommand logic simply, we'll instantiate it and execute it for each node sequentially.
        $pushCmd = new DeployPushCommand();
        $baseArgs = ['spp.php', 'deploy:push'];

        // Pass through any flags like --force, --dry-run, --no-db
        $flags = [];
        for ($i = 3; $i < count($args); $i++) {
            $flags[] = $args[$i];
        }

        if (!in_array('--force', $flags) && !in_array('-y', $flags)) {
            echo "⚠️  WARNING: You are about to deploy to a cluster of " . count($nodes) . " servers.\n";
            echo "❓ Proceed with cluster deployment? [Y/n] ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            if (strtolower($line) === 'n' || strtolower($line) === 'no') {
                echo "⛔ Cluster deployment aborted.\n";
                return;
            }
            $flags[] = '--force'; // automatically pass force to sub-commands
        }

        $successCount = 0;
        foreach ($nodes as $index => $node) {
            echo "\n--------------------------------------------------\n";
            echo "📍 [Node " . ($index + 1) . "/" . count($nodes) . "] Deploying to: {$node}\n";
            echo "--------------------------------------------------\n";

            $nodeArgs = array_merge($baseArgs, [$node], $flags);

            try {
                // Execute the push command for this specific target
                $pushCmd->execute($nodeArgs);
                $successCount++;
            } catch (\Exception $e) {
                echo "❌ CRITICAL: Deployment to node {$node} threw an exception: " . $e->getMessage() . "\n";
                echo "🛑 Stopping cluster deployment roll-out.\n";
                break;
            }
        }

        echo "\n==================================================\n";
        echo "🏁 Cluster Deployment Completed.\n";
        echo "   Nodes Updated: {$successCount} / " . count($nodes) . "\n";
        if ($successCount !== count($nodes)) {
            echo "⚠️  WARNING: Not all nodes in the cluster were updated successfully.\n";
        }
    }

    public function getName(): string
    {
        return 'deploy:cluster';
    }

    public function getDescription(): string
    {
        return 'Deploy to a multi-server cluster sequentially';
    }
}
