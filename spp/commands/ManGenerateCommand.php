<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ManGenerateCommand extends Command
{
    protected string $name = 'man:generate';
    protected string $description = 'Generate highly detailed man-pages in Markdown and UNIX roff formats';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $force = in_array('--force', $args);

        $baseDir = dirname(SPP_BASE_DIR); // SPP_BASE_DIR is usually spp/
        $docsDir = $baseDir . '/docs/commands';
        $manDir = $baseDir . '/man/man1';

        if (!is_dir($docsDir)) mkdir($docsDir, 0755, true);
        if (!is_dir($manDir)) mkdir($manDir, 0755, true);

        $commands = \SPP\CLI\CommandManager::discover();
        
        $mdIndex = "# SPP CLI Manual\n\nDetailed reference for all `spp.php` commands.\n\n## Table of Contents\n";
        ksort($commands);

        foreach ($commands as $name => $cmd) {
            $safeAnchor = preg_replace('/[^a-z0-9]/', '', strtolower($name));
            $mdIndex .= "- [`{$name}`](#{$safeAnchor})\n";
        }
        $mdIndex .= "\n---\n\n";

        foreach ($commands as $name => $cmd) {
            $safeName = str_replace(':', '-', $name);
            $mdFile = $docsDir . "/{$safeName}.md";
            $fallbackMdFile = $docsDir . "/spp-{$safeName}.md"; // Subagents often prefix with spp-
            
            // Allow overrides via manually authored AI prose
            if (!$force && file_exists($mdFile)) {
                $mdOut = file_get_contents($mdFile);
            } elseif (!$force && file_exists($fallbackMdFile)) {
                $mdOut = file_get_contents($fallbackMdFile);
            } else {
                $details = $this->analyzeCommand($cmd);
                $mdOut = $this->buildMarkdown($name, $details);
                file_put_contents($mdFile, $mdOut);
            }

            $roffOut = $this->convertMarkdownToRoff($name, $mdOut);
            file_put_contents($manDir . "/spp-{$safeName}.1", $roffOut);
            
            $mdIndex .= $mdOut . "\n---\n\n";
        }

        file_put_contents($baseDir . '/docs/spp-cli-manual.md', $mdIndex);
        echo "Generated exhaustive documentation successfully for " . count($commands) . " commands!\n";
        echo "- UNIX man pages saved to: $manDir/spp-*.1\n";
    }

    private function convertMarkdownToRoff(string $name, string $md): string
    {
        $safeName = str_replace(':', '-', $name);
        $date = date('d F Y');
        $roff = ".TH SPP-{$safeName} 1 \"{$date}\" \"SPP Framework\" \"SPP CLI Manual\"\n";
        
        // Basic Markdown to Roff conversion
        $lines = explode("\n", $md);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                $roff .= ".PP\n";
                continue;
            }
            if (str_starts_with($line, '## `')) {
                $roff .= ".SH NAME\n{$name}\n";
            } elseif (str_starts_with($line, '**Purpose**:')) {
                $desc = trim(substr($line, 12));
                $roff .= " \\- {$desc}\n.SH DESCRIPTION\n{$desc}\n";
            } elseif (str_starts_with($line, '**Description**:')) {
                $desc = trim(substr($line, 16));
                $roff .= " \\- {$desc}\n.SH DESCRIPTION\n{$desc}\n";
            } elseif (str_starts_with($line, '### Synopsis')) {
                $roff .= ".SH SYNOPSIS\n";
            } elseif (str_starts_with($line, '### Extended Usage')) {
                $roff .= ".SH EXTENDED USAGE\n";
            } elseif (str_starts_with($line, '### Options Available') || str_starts_with($line, '### Options')) {
                $roff .= ".SH OPTIONS\n";
            } elseif (str_starts_with($line, '### Under the Hood Activity') || str_starts_with($line, '### Under the Hood')) {
                $roff .= ".SH UNDER THE HOOD\n";
            } elseif (str_starts_with($line, '- ')) {
                $roff .= "\\(bu " . substr($line, 2) . "\n.br\n";
            } elseif (str_starts_with($line, '```')) {
                // Ignore code fences
            } else {
                $line = str_replace('`', '', $line);
                $line = str_replace('**', '', $line);
                $roff .= $line . "\n";
            }
        }
        return $roff;
    }

    private function analyzeCommand($cmd): array
    {
        $ref = new \ReflectionClass($cmd);
        $fileName = $ref->getFileName();
        $source = file_get_contents($fileName);
        
        $desc = $cmd->getDescription();
        $help = method_exists($cmd, 'getHelp') ? $cmd->getHelp() : '';

        // Options Extraction
        $options = [];
        preg_match_all("/str_starts_with\(\\$[a-zA-Z0-9_]+,\s*'(--[^']+)'\)/", $source, $m1);
        preg_match_all("/\\$[a-zA-Z0-9_]+\s*===\s*'(--[^']+)'/", $source, $m2);
        preg_match_all("/in_array\('(--[^']+)'/", $source, $m3);
        preg_match_all("/isset\(\\$[a-zA-Z0-9_]+\['([^']+)'\]\)/", $source, $m4);
        
        $foundOptions = array_unique(array_merge($m1[1] ?? [], $m2[1] ?? [], $m3[1] ?? []));
        
        if (!empty($m4[1])) {
            foreach($m4[1] as $optKey) {
                if ($optKey !== 'options' && $optKey !== 'arguments') {
                    $foundOptions[] = "--" . $optKey;
                }
            }
            $foundOptions = array_unique($foundOptions);
        }

        foreach($foundOptions as $opt) {
            if (str_ends_with($opt, '=')) {
                $options[$opt] = "Expects a value. Extracted via static analysis.";
            } else {
                $options[$opt] = "Boolean flag or option. Extracted via static analysis.";
            }
        }

        // Under the Hood Extractions
        $hood = [];

        if (preg_match("/(?:\\\$db->|DB::|SPP\\\\DB\\\\)/i", $source)) {
            $hood[] = "Interacts with the SPP relational database layer.";
        }
        if (preg_match("/(?:file_put_contents|fopen|mkdir|copy|unlink)\(/i", $source)) {
            $hood[] = "Performs direct filesystem modifications (create/write/delete).";
        }
        if (preg_match("/(?:exec|shell_exec|system|passthru)\(/i", $source)) {
            $hood[] = "Executes external system binaries or shell commands.";
        }
        if (preg_match_all("/loadModule\('([^']+)'\)/i", $source, $mMod)) {
            $modules = array_unique($mMod[1]);
            $hood[] = "Dynamically loads SPP kernel modules: " . implode(", ", $modules) . ".";
        }
        if (preg_match("/Scheduler::withContext\(/i", $source)) {
            $hood[] = "Bootstraps a full application execution context via Scheduler.";
        }
        if (preg_match_all("/new\s+([A-Za-z0-9_\\\\]+)/", $source, $mClasses)) {
            $classes = array_filter(array_unique($mClasses[1]), function($c) { 
                return !in_array($c, ['Exception','RuntimeException','InvalidArgumentException','ReflectionClass', '\\Exception', 'stdClass']); 
            });
            if (!empty($classes)) {
                $topClasses = array_slice($classes, 0, 8);
                $hood[] = "Instantiates internal components: " . implode(", ", $topClasses) . ".";
            }
        }
        if (preg_match("/curl_init/i", $source) || preg_match("/file_get_contents\(['\"]http/i", $source)) {
            $hood[] = "Makes outbound HTTP requests to external APIs or services.";
        }
        if (preg_match("/Redis::/i", $source) || preg_match("/Cache::/i", $source)) {
            $hood[] = "Interacts with the application cache layer (Redis/Memcached).";
        }

        if (empty($hood)) {
            $hood[] = "Executes native PHP logic without major side-effects.";
        }

        return [
            'description' => $desc,
            'help' => $help,
            'options' => $options,
            'hood' => $hood
        ];
    }

    private function buildMarkdown(string $name, array $details): string
    {
        $md = "## `{$name}`\n\n";
        $md .= "**Purpose**: {$details['description']}\n\n";
        $md .= "### Synopsis\n";
        $md .= "```bash\nphp spp.php {$name} [OPTIONS]\n```\n\n";
        
        if ($details['help']) {
            $md .= "### Extended Usage\n```text\n{$details['help']}\n```\n\n";
        }

        $md .= "### Options Available\n";
        if (empty($details['options'])) {
            $md .= "No static options detected for this command.\n\n";
        } else {
            foreach ($details['options'] as $opt => $desc) {
                $md .= "- `{$opt}` : {$desc}\n";
            }
            $md .= "\n";
        }

        $md .= "### Under the Hood Activity\n";
        $md .= "Based on static analysis of the command's source code, invoking this command performs the following operations:\n";
        foreach ($details['hood'] as $h) {
            $md .= "- {$h}\n";
        }
        $md .= "\n";

        return $md;
    }
}
