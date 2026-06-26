<?php
$baseDir = dirname(__DIR__); // Assuming script is in scratch/
$docsDir = $baseDir . '/docs/commands';
if (!is_dir($docsDir)) mkdir($docsDir, 0755, true);

$nsDescriptions = [
    'ai' => 'AI Provider and Prompt configuration',
    'api' => 'REST API endpoints and key management',
    'app' => 'Application context and lifecycle management',
    'audit' => 'System audit logs and lineages',
    'auth' => 'Authentication, roles, rights, and user identity',
    'blade' => 'Blade template engine commands',
    'build' => 'Frontend asset and build pipelines',
    'cache' => 'Application and Redis cache management',
    'cli' => 'CLI environment utilities',
    'config' => 'Framework configuration management',
    'create' => 'Scaffolding tools for new elements',
    'cron' => 'Cron job and scheduled task execution',
    'db' => 'Database migrations and verifications',
    'dbsettings' => 'Database settings import/export',
    'delete' => 'Destructive operations for entities and apps',
    'di' => 'Dependency Injection container tools',
    'diff' => 'State comparison, patches, and rollbacks',
    'drishyam' => 'Drishyam theme and UI rendering engine',
    'ent' => 'SPP Entity management and querying',
    'env' => 'Environment variables and system status',
    'event' => 'Event dispatcher and listeners',
    'ext' => 'Extension/Plugin management',
    'frontend' => 'Frontend CDN and debug toggles',
    'group' => 'Shared resource and group management',
    'i18n' => 'Internationalization export/import',
    'import' => 'Component import utilities',
    'interdb' => 'InterDB distributed database tools',
    'lang' => 'Translation and locale management',
    'lekhak' => 'Lekhak CMS management',
    'live' => 'LiveSync and WebSockets',
    'logger' => 'Application log viewing and management',
    'make' => 'Code generators and scaffolders',
    'man' => 'Manual page generation',
    'manifest' => 'Tool autodiscovery exports',
    'marketing' => 'Marketing automation and campaigns',
    'middleware' => 'HTTP middleware pipelines',
    'migrate' => 'State deployment and migrations',
    'module' => 'Kernel module management',
    'polyglot' => 'External language service execution',
    'profile' => 'Performance profiling and traces',
    'pwa' => 'Progressive Web App generation',
    'queue' => 'Background job queues and workers',
    'session' => 'Session lifecycle management',
    'site' => 'Site installation and profiles',
    'storage' => 'Filesystem and storage sync tools',
    'sys' => 'System updates, bridges, and toggles',
    'test' => 'Parikshak Evolutionary Testing suite',
    'theme' => 'Theme adapter switching',
    'ui' => 'Legacy UI commands',
    'userprofile' => 'Extended user metadata schemas',
    'ux' => 'Reactive SPP-UX component tools',
    'verify' => 'Stack sovereignty verifications',
    'view' => 'Page routes and AJAX service discovery',
    'wizard' => 'Multi-step form configuration',
    'xdb' => 'SPPXDB XML database shell and queries'
];

foreach ($nsDescriptions as $ns => $desc) {
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

echo "Generated " . count($nsDescriptions) . " namespace manuals.\n";
