<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use App\SPPDocs\Storage\JsonStorageDriver;
use App\SPPDocs\Storage\SqliteStorageDriver;
use Symfony\Component\Yaml\Yaml;

/**
 * Class SPPDocsStorageMigrateCommand
 * Migrates an SPPDocs project between Flat-File JSON and SQLite storage backends with zero data loss.
 */
class SPPDocsStorageMigrateCommand extends Command
{
    protected string $name = 'sppdocs:storage:migrate';
    protected string $description = 'Migrate SPPDocs project issues between Flat-File JSON and SQLite storage';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $projectArg = null;
        $targetArg = 'sqlite';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--project=')) {
                $projectArg = substr($arg, 10);
            } elseif (str_starts_with($arg, '--to=')) {
                $targetArg = strtolower(substr($arg, 5));
            }
        }

        if (!$projectArg) {
            echo "❌ Error: Missing required --project argument. Usage: php spp.php sppdocs:storage:migrate --project=demo-app --to=sqlite\n";
            return;
        }

        if (!in_array($targetArg, ['sqlite', 'json'])) {
            echo "❌ Error: Invalid --to target '{$targetArg}'. Valid options: sqlite, json\n";
            return;
        }

        $appDir = dirname(__DIR__, 2);
        $projectYml = $appDir . '/docs/' . $projectArg . '/project.yml';
        if (!file_exists($projectYml)) {
            echo "❌ Error: Project configuration not found at {$projectYml}\n";
            return;
        }

        $projectConfig = Yaml::parseFile($projectYml) ?: [];
        $issuesDir = $appDir . '/docs/' . $projectArg . '/issues';
        if (!is_dir($issuesDir)) {
            @mkdir($issuesDir, 0777, true);
        }

        echo "🚀 Starting SPPDocs Storage Migration for '{$projectArg}'...\n";
        echo "   Target Storage Engine: " . strtoupper($targetArg) . "\n";
        echo "   Directory: {$issuesDir}\n\n";

        $jsonDriver = new JsonStorageDriver($issuesDir);
        $sqliteDriver = new SqliteStorageDriver($issuesDir);

        $startTime = microtime(true);

        if ($targetArg === 'sqlite') {
            $issues = $jsonDriver->loadAll();
            $count = count($issues);
            echo "   Found {$count} issues in Flat-File JSON storage.\n";

            $migrated = 0;
            foreach ($issues as $issue) {
                // Fetch full issue details (with comments and time logs)
                $full = $jsonDriver->find($issue['id']);
                if ($full) {
                    $sqliteDriver->save($issue['id'], $full);
                    $migrated++;
                }
            }

            // Update project.yml
            $projectConfig['storage'] = 'sqlite';
            file_put_contents($projectYml, Yaml::dump($projectConfig, 4));

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            echo "✅ Successfully migrated {$migrated} issues to SQLite WAL database in {$duration} ms!\n";
            echo "   Updated {$projectYml} (storage: sqlite)\n";

        } else {
            $issues = $sqliteDriver->loadAll();
            $count = count($issues);
            echo "   Found {$count} issues in SQLite storage.\n";

            $migrated = 0;
            foreach ($issues as $issue) {
                $full = $sqliteDriver->find($issue['id']);
                if ($full) {
                    $jsonDriver->save($issue['id'], $full);
                    $migrated++;
                }
            }

            $jsonDriver->rebuildIndex();

            // Update project.yml
            $projectConfig['storage'] = 'json';
            file_put_contents($projectYml, Yaml::dump($projectConfig, 4));

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            echo "✅ Successfully exported {$migrated} issues to Flat-File JSON + index.json in {$duration} ms!\n";
            echo "   Updated {$projectYml} (storage: json)\n";
        }
    }
}
