<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use App\SPPDocs\Services\VectorSearchEngine;

/**
 * Class SPPDocsAiIndexCommand
 * Indexes markdown documentation pages into semantic chunk vector signatures for AI search & RAG.
 */
class SPPDocsAiIndexCommand extends Command
{
    protected string $name = 'sppdocs:ai:index';
    protected string $description = 'Index documentation pages into semantic chunk vector signatures for AI search and RAG';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $project = 'demo-app';
        $provider = 'offline';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--project=')) {
                $project = substr($arg, 10);
            } elseif (str_starts_with($arg, '--provider=')) {
                $provider = substr($arg, 11);
            }
        }

        echo "🧠 SPPDocs Semantic AI Vector Indexer\n";
        echo "   Target Project: {$project}\n";
        echo "   AI Provider:    {$provider}\n\n";

        $res = VectorSearchEngine::indexProject($project, $provider);

        if ($res['success']) {
            echo "✅ AI Vector Indexing Complete!\n";
            echo "   Pages Scanned:  " . ($res['pages'] ?? 0) . "\n";
            echo "   Chunks Indexed: " . ($res['chunks'] ?? 0) . "\n";
            if (!empty($res['index_path'])) {
                echo "   Index Location: " . $res['index_path'] . "\n";
            }
        } else {
            echo "❌ Indexing Failed: " . ($res['message'] ?? 'Unknown error') . "\n";
        }
    }
}
