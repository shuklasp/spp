<?php
$baseDir = dirname(__DIR__);
$docsDir = $baseDir . '/docs/commands';

$missing = [
    'admin' => 'Administration dashboard and backend utilities',
    'bridge' => 'Language bridges and polyglot adapters',
    'component' => 'Frontend/UI component management',
    'deploy' => 'CI/CD, environment syncing, and artifact deployments',
    'docs' => 'Documentation generation and parsing',
    'entity' => 'Entity and ORM management',
    'form' => 'Form builders and validation',
    'schedule' => 'Job scheduling and task running',
    'service' => 'Service layer and dependency injection'
];

foreach ($missing as $ns => $desc) {
    $title = strtoupper($ns) . " NAMESPACE";
    $md = "## NAME\n\n";
    $md .= "**{$ns}** - {$desc}\n\n";
    $md .= "## PURPOSE\n\n";
    $md .= "The `{$ns}` namespace is a logical grouping of SPP CLI commands related to {$desc}. This namespace provides a suite of administrative and operational tools specifically designed to interact with the underlying {$ns} subsystems of the framework.\n\n";
    $md .= "## UNDER THE HOOD ACTIVITY\n\n";
    $md .= "When invoking commands within the `{$ns}` namespace, the CLI router isolates execution to specific modules and classes optimized for {$desc}. This modular architectural grouping prevents command collisions and ensures that each subsystem operates within its dedicated execution context.\n\n";
    $md .= "To view the exhaustive details, options, and deep functionality of any specific command within this group, execute `php spp.php man {$ns}:<subcommand>`.\n";
    
    file_put_contents($docsDir . "/ns-{$ns}.md", $md);
}

echo "Generated missing namespace manuals.\n";
