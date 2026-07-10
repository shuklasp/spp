<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ManCommand extends Command
{
    protected string $name = 'man';
    protected string $description = 'Format and display manual pages for SPP commands';

    public function execute(array $args): void
    {
        $targetCmdName = $args[2] ?? null;

        if (empty($targetCmdName)) {
            echo "What manual page do you want?\n\n";
            $commands = \SPP\CLI\CommandManager::discover();
            
            $namespaces = [];
            $rootCommands = [];
            
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
            
            foreach ($commands as $cmdName => $cmdObj) {
                if (strpos($cmdName, ':') !== false) {
                    $parts = explode(':', $cmdName);
                    $ns = $parts[0];
                    if (!isset($namespaces[$ns])) {
                        $namespaces[$ns] = 0;
                    }
                    $namespaces[$ns]++;
                } else {
                    $rootCommands[$cmdName] = $cmdObj->getDescription();
                }
            }
            
            ksort($namespaces);
            ksort($rootCommands);
            
            echo "\033[1mAVAILABLE NAMESPACES\033[0m\n";
            foreach ($namespaces as $ns => $count) {
                $desc = $nsDescriptions[$ns] ?? 'Namespace for ' . $ns . ' commands';
                echo "       \033[1m" . str_pad($ns, 15) . "\033[0m {$desc} ({$count} commands)\n";
            }
            echo "\n";
            
            echo "\033[1mROOT COMMANDS\033[0m\n";
            foreach ($rootCommands as $name => $desc) {
                echo "       \033[1m" . str_pad($name, 15) . "\033[0m {$desc}\n";
            }
            echo "\n";
            echo "For example, try 'php spp.php man cache' or 'php spp.php man cache:clear'.\n";
            return;
        }

        $commands = \SPP\CLI\CommandManager::discover();
        if (!isset($commands[$targetCmdName])) {
            // Check if this is a namespace prefix
            $prefix = $targetCmdName . ':';
            $matches = [];
            foreach ($commands as $cmdName => $cmdObj) {
                if (str_starts_with($cmdName, $prefix)) {
                    $matches[$cmdName] = $cmdObj;
                }
            }

            if (!empty($matches)) {
                $baseDir = dirname(SPP_BASE_DIR);
                $nsMdFile = $baseDir . "/docs/commands/ns-{$targetCmdName}.md";
                if (file_exists($nsMdFile)) {
                    $mdOut = file_get_contents($nsMdFile);
                    $mdOut = preg_replace('/^### (.*)$/m', "\033[1;36m$1\033[0m", $mdOut);
                    $mdOut = preg_replace('/^## (.*)$/m', "\033[1;34m$1\033[0m", $mdOut);
                    $mdOut = preg_replace('/\*\*(.*?)\*\*/', "\033[1m$1\033[0m", $mdOut);
                    $mdOut = preg_replace('/`(.*?)`/', "\033[33m$1\033[0m", $mdOut);
                    echo $mdOut . "\n\n";
                } else {
                    echo "\033[1mNAME\033[0m\n";
                    echo "       \033[1m{$targetCmdName}\033[0m - Namespace grouping for '{$targetCmdName}:*' commands\n\n";
                }

                echo "\033[1mAVAILABLE COMMANDS\033[0m\n";
                ksort($matches);
                foreach ($matches as $cmdName => $cmdObj) {
                    $desc = $cmdObj->getDescription();
                    echo "       \033[1m{$cmdName}\033[0m\n";
                    echo "              {$desc}\n";
                }
                echo "\n";
                echo "Use 'php spp.php man <command>' to view detailed usage for a specific command.\n";
                return;
            }

            // Check if a dedicated markdown manual exists for built-in or custom commands like tab
            $customMdFile = dirname(SPP_BASE_DIR) . "/docs/commands/{$targetCmdName}.md";
            if (file_exists($customMdFile)) {
                $mdOut = file_get_contents($customMdFile);
                $mdOut = preg_replace('/^### (.*)$/m', "\033[1;36m$1\033[0m", $mdOut);
                $mdOut = preg_replace('/^## (.*)$/m', "\033[1;34m$1\033[0m", $mdOut);
                $mdOut = preg_replace('/\*\*(.*?)\*\*/', "\033[1m$1\033[0m", $mdOut);
                $mdOut = preg_replace('/`(.*?)`/', "\033[33m$1\033[0m", $mdOut);
                echo $mdOut . "\n";
                return;
            }

            echo "No manual entry for {$targetCmdName}\n";
            return;
        }

        $cmd = $commands[$targetCmdName];
        $name = $cmd->getName();
        $safeName = str_replace(':', '-', $name);
        $desc = $cmd->getDescription();

        $baseDir = dirname(SPP_BASE_DIR);
        $docsDir = $baseDir . '/docs/commands';
        
        $mdFile1 = $docsDir . "/{$safeName}.md";
        $mdFile2 = $docsDir . "/spp-{$safeName}.md";
        
        $mdOut = null;
        if (file_exists($mdFile1)) {
            $mdOut = file_get_contents($mdFile1);
        } elseif (file_exists($mdFile2)) {
            $mdOut = file_get_contents($mdFile2);
        }

        if ($mdOut !== null) {
            // Apply some basic terminal formatting for headers
            $mdOut = preg_replace('/^### (.*)$/m', "\033[1;36m$1\033[0m", $mdOut);
            $mdOut = preg_replace('/^## (.*)$/m', "\033[1;34m$1\033[0m", $mdOut);
            $mdOut = preg_replace('/\*\*(.*?)\*\*/', "\033[1m$1\033[0m", $mdOut);
            $mdOut = preg_replace('/`(.*?)`/', "\033[33m$1\033[0m", $mdOut);
            echo $mdOut . "\n";
            return;
        }

        // Fallback if not generated
        $help = $cmd->getHelp();

        $out = "";
        $out .= "\033[1mNAME\033[0m\n";
        $out .= "       \033[1m{$name}\033[0m - {$desc}\n\n";
        
        $out .= "\033[1mSYNOPSIS\033[0m\n";
        $out .= "       \033[1mphp spp.php {$name}\033[0m [OPTIONS]\n\n";

        $out .= "\033[1mDESCRIPTION\033[0m\n";
        if ($help) {
            $lines = explode("\n", $help);
            foreach ($lines as $line) {
                $out .= "       " . trim($line) . "\n";
            }
        } else {
            $out .= "       {$desc}\n";
            $out .= "       (No extended usage documentation available. Run 'php spp.php man:generate' to build detailed manuals).\n";
        }
        $out .= "\n";

        echo $out;
    }
}
