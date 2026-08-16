<?php
namespace App\Lekhak\Commands;

use SPP\CLI\Command;
use SPPMod\Lekhak\Core\DocGen;
use SPPMod\Lekhak\Core\LekhakNode;

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
        
        $docGenPath = dirname(SPP_BASE_DIR) . '/src/lekhak/modules/lekhak/src/Core/DocGen.php';
        $lekhakNodePath = dirname(SPP_BASE_DIR) . '/src/lekhak/modules/lekhak/src/Core/LekhakNode.php';
        if (file_exists($docGenPath)) {
            require_once $lekhakNodePath;
            require_once $docGenPath;
        }

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
            $entity = new LekhakNode();
            $entity->title = $node->title;
            $entity->body = $node->body;
            $entity->langcode = 'en';
            
            $alias = "$version/api/" . str_replace('\\', '/', $node->title);
            $entity->alias = $alias;
            
            try {
                $entity->save(); // Initial save
                $entity->applyTransition('published');
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
            ],
            [
                'title' => 'Zero-Trust IAM Architecture',
                'alias' => "$version/tutorial/iam-zero-trust",
                'body' => '<h1>Zero-Trust IAM</h1><p>The SPPAuth module provides a full Zero-Trust Identity Provider. Features include:</p><ul><li><b>MFA</b>: Enable via Admin UI, requires TOTP Authenticator.</li><li><b>Magic Links</b>: Generate via <code>php spp.php auth:magic-link</code> for passwordless login.</li><li><b>ABAC</b>: Write JSON policy conditions dynamically.</li><li><b>OAuth 2.0</b>: Create client apps with <code>php spp.php oauth:client:create</code>.</li><li><b>SCIM 2.0</b>: Automated user provisioning at the <code>/Users</code> endpoint.</li></ul>'
            ]
        ];

        foreach ($tutorials as $t) {
            $entity = new LekhakNode();
            $entity->title = $t['title'];
            $entity->body = $t['body'];
            $entity->langcode = 'en';
            $entity->alias = $t['alias'];
            $entity->save();
            $entity->applyTransition('published');
            echo "Saved Tutorial: {$t['title']}\n";
        }

        echo "Documentation generation complete.\n";
    }
}
