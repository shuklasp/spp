<?php
define('SPP_BASE_DIR', __DIR__);
require_once __DIR__ . '/spp/spp.php';

use SPP\CLI\CommandManager;

class AutomatedManGenerator {
    public static function generate() {
        $commands = CommandManager::discover();
        $manDir = __DIR__ . '/man/man1';
        $docsDir = __DIR__ . '/docs/commands';
        
        if (!is_dir($manDir)) mkdir($manDir, 0755, true);
        if (!is_dir($docsDir)) mkdir($docsDir, 0755, true);

        $mdIndex = "# SPP CLI Manual\n\nDetailed reference for all `spp.php` commands, generated via static code analysis.\n\n## Table of Contents\n";
        ksort($commands);

        foreach ($commands as $name => $cmd) {
            $safeAnchor = preg_replace('/[^a-z0-9]/', '', strtolower($name));
            $mdIndex .= "- [`{$name}`](#{$safeAnchor})\n";
        }
        $mdIndex .= "\n---\n\n";

        foreach ($commands as $name => $cmd) {
            $details = self::analyzeCommand($cmd);
            $mdOut = self::buildMarkdown($name, $details);
            $roffOut = self::buildRoff($name, $details);

            $safeName = str_replace(':', '-', $name);
            file_put_contents($manDir . "/spp-{$safeName}.1", $roffOut);
            file_put_contents($docsDir . "/{$safeName}.md", $mdOut);
            
            $mdIndex .= $mdOut . "\n---\n\n";
            echo "Generated man page for: {$name}\n";
        }

        file_put_contents(__DIR__ . '/docs/spp-cli-manual.md', $mdIndex);
        echo "\nSuccessfully generated all manual pages.\n";
    }

    private static function analyzeCommand($cmd) {
        $ref = new \ReflectionClass($cmd);
        $fileName = $ref->getFileName();
        $source = file_get_contents($fileName);
        
        $desc = $cmd->getDescription();
        $help = method_exists($cmd, 'getHelp') ? $cmd->getHelp() : '';

        // Options Extraction
        $options = [];
        preg_match_all("/str_starts_with\(\\\$[a-zA-Z0-9_]+,\s*'(--[^']+)'\)/", $source, $m1);
        preg_match_all("/\\\$[a-zA-Z0-9_]+\s*===\s*'(--[^']+)'/", $source, $m2);
        preg_match_all("/in_array\('(--[^']+)'/", $source, $m3);
        $foundOptions = array_unique(array_merge($m1[1] ?? [], $m2[1] ?? [], $m3[1] ?? []));
        
        foreach($foundOptions as $opt) {
            if (str_ends_with($opt, '=')) {
                $options[$opt] = "Expects a value. Extracted via static analysis from " . basename($fileName);
            } else {
                $options[$opt] = "Boolean flag. Extracted via static analysis from " . basename($fileName);
            }
        }

        // Under the Hood Extractions
        $hood = [];

        // DB calls
        if (preg_match("/(?:\\\$db->|DB::|SPP\\\\DB\\\\)/i", $source)) {
            $hood[] = "Interacts with the SPP database layer directly.";
        }
        // File IO
        if (preg_match("/(?:file_put_contents|fopen|mkdir|copy|unlink)\(/i", $source)) {
            $hood[] = "Performs raw filesystem modifications (create/write/delete).";
        }
        // Exec
        if (preg_match("/(?:exec|shell_exec|system|passthru)\(/i", $source)) {
            $hood[] = "Executes external system binaries or shell commands.";
        }
        // Module Loading
        if (preg_match_all("/loadModule\('([^']+)'\)/i", $source, $mMod)) {
            $modules = array_unique($mMod[1]);
            $hood[] = "Dynamically loads kernel modules: " . implode(", ", $modules) . ".";
        }
        // Scheduler Context
        if (preg_match("/Scheduler::withContext\(/i", $source)) {
            $hood[] = "Bootstraps a full application execution context (Scheduler::withContext).";
        }
        // Classes used
        if (preg_match_all("/new\s+([A-Za-z0-9_\\\\]+)/", $source, $mClasses)) {
            $classes = array_filter(array_unique($mClasses[1]), function($c) { return !in_array($c, ['Exception','RuntimeException','InvalidArgumentException','ReflectionClass']); });
            if (!empty($classes)) {
                // Keep top 5 to avoid bloat
                $topClasses = array_slice($classes, 0, 5);
                $hood[] = "Instantiates key components: " . implode(", ", $topClasses) . ".";
            }
        }

        if (empty($hood)) {
            $hood[] = "Executes native PHP logic without major side-effects or external dependencies.";
        }

        return [
            'description' => $desc,
            'help' => $help,
            'options' => $options,
            'hood' => $hood
        ];
    }

    private static function buildMarkdown($name, $details) {
        $md = "## `{$name}`\n\n";
        $md .= "**Description**: {$details['description']}\n\n";
        $md .= "### Synopsis\n";
        $md .= "```bash\nphp spp.php {$name} [OPTIONS]\n```\n\n";
        
        if ($details['help']) {
            $md .= "### Extended Usage\n```text\n{$details['help']}\n```\n\n";
        }

        $md .= "### Options\n";
        if (empty($details['options'])) {
            $md .= "No static options detected.\n\n";
        } else {
            foreach ($details['options'] as $opt => $desc) {
                $md .= "- `{$opt}` : {$desc}\n";
            }
            $md .= "\n";
        }

        $md .= "### Under the Hood\n";
        $md .= "Based on static analysis of the command's source code:\n";
        foreach ($details['hood'] as $h) {
            $md .= "- {$h}\n";
        }
        $md .= "\n";

        return $md;
    }

    private static function buildRoff($name, $details) {
        $safeName = str_replace(':', '-', $name);
        $date = date('d F Y');
        $roff = ".TH SPP-{$safeName} 1 \"{$date}\" \"SPP Framework\" \"SPP CLI Manual\"\n";
        $roff .= ".SH NAME\n";
        $roff .= "spp-{$safeName} \\- {$details['description']}\n";
        $roff .= ".SH SYNOPSIS\n";
        $roff .= ".B php spp.php {$name}\n";
        $roff .= "[OPTIONS]\n";
        
        $roff .= ".SH DESCRIPTION\n";
        $roff .= "{$details['description']}\n.PP\n";
        
        if ($details['help']) {
            $escapedHelp = str_replace("\n", "\n.br\n", $details['help']);
            $roff .= $escapedHelp . "\n.PP\n";
        }

        $roff .= ".SH OPTIONS\n";
        if (empty($details['options'])) {
            $roff .= "No explicit options statically detected.\n";
        } else {
            foreach ($details['options'] as $opt => $desc) {
                $roff .= ".TP\n.B {$opt}\n{$desc}\n";
            }
        }
        
        $roff .= ".SH UNDER THE HOOD\n";
        $roff .= "Static analysis reveals the following runtime behaviors:\n.PP\n";
        foreach ($details['hood'] as $h) {
            $roff .= "\\(bu {$h}\n.br\n";
        }

        return $roff;
    }
}

AutomatedManGenerator::generate();
