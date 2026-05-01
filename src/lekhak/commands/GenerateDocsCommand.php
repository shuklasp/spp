<?php
namespace App\Lekhak\Commands;

use SPP\CLI\Command;
use SPPMod\Lekhak\Core\DocGen;
use App\Lekhak\Entities\Node;

/**
 * Class GenerateDocsCommand
 * Scans the SPP framework and modules to generate API documentation nodes.
 */
class GenerateDocsCommand extends Command
{
    protected string $name = 'lekhak:generate-docs';
    protected string $description = 'Generates documentation nodes for SPP Core and Modules.';

    public function execute(array $args): void
    {
        $version = $args['version'] ?? '1.0';
        $gen = new DocGen($version);

        echo "Generating documentation for SPP Core (Version: $version)...\n";

        // 1. Scan Core
        $coreNodes = $gen->generateFromDir(SPP_CORE_DIR, 'Core');

        // 2. Scan Modules
        echo "Generating documentation for SPP Modules...\n";
        $modNodes = $gen->generateFromDir(SPP_MODULES_DIR, 'Modules');

        $allNodes = array_merge($coreNodes, $modNodes);

        echo "Saving " . count($allNodes) . " nodes to database...\n";

        foreach ($allNodes as $node) {
            $entity = new Node();
            $entity->title = $node->title;
            $entity->body = $node->body;
            $entity->status = 'published';
            $entity->langcode = 'en';
            
            $alias = "$version/api/" . str_replace('\\', '/', $node->title);
            $entity->alias = $alias;
            
            try {
                $entity->save();
                echo "Saved: {$node->title} -> {$alias}\n";
            } catch (\Exception $e) {
                echo "Error saving {$node->title}: " . $e->getMessage() . "\n";
            }
        }

        // 3. Create Tutorial Nodes
        $tutorials = [
            [
                'title' => 'Getting Started with SPP',
                'alias' => "$version/tutorial/intro",
                'body' => '<h1>Welcome to SPP</h1><p>SPP is a modern, modular PHP framework designed for high performance and premium UX.</p>'
            ],
            [
                'title' => 'Creating your first App',
                'alias' => "$version/tutorial/make-app",
                'body' => '<h1>Making an App</h1><p>Use <code>php spp.php make:app MyName</code> to get started.</p>'
            ]
        ];

        foreach ($tutorials as $t) {
            $entity = new Node();
            $entity->title = $t['title'];
            $entity->body = $t['body'];
            $entity->status = 'published';
            $entity->langcode = 'en';
            $entity->alias = $t['alias'];
            $entity->save();
            echo "Saved Tutorial: {$t['title']}\n";
        }

        echo "Documentation generation complete.\n";
    }
}
