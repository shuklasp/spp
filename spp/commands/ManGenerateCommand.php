<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ManGenerateCommand extends Command
{
    protected string $name = 'man:generate';
    protected string $description = 'Generate man-pages in Markdown and UNIX roff formats';

    public function execute(array $args): void
    {
        $baseDir = dirname(SPP_BASE_DIR);
        $docsDir = $baseDir . '/docs';
        $manDir = $baseDir . '/man/man1';

        if (!is_dir($docsDir)) mkdir($docsDir, 0755, true);
        if (!is_dir($manDir)) mkdir($manDir, 0755, true);

        $commands = \SPP\CLI\CommandManager::discover();
        
        $mdOut = "# SPP CLI Manual\n\n";
        $mdOut .= "Detailed reference for all `spp.php` commands.\n\n";
        $mdOut .= "## Table of Contents\n";
        
        ksort($commands);

        foreach ($commands as $name => $cmd) {
            $safeAnchor = preg_replace('/[^a-z0-9]/', '', strtolower($name));
            $mdOut .= "- [`{$name}`](#{$safeAnchor})\n";
        }
        $mdOut .= "\n---\n\n";

        foreach ($commands as $name => $cmd) {
            $desc = $cmd->getDescription();
            $help = trim($cmd->getHelp());

            // Markdown section
            $mdOut .= "## `{$name}`\n\n";
            $mdOut .= "**Description**: {$desc}\n\n";
            $mdOut .= "### Synopsis\n";
            $mdOut .= "```bash\nphp spp.php {$name} [OPTIONS]\n```\n\n";
            if ($help) {
                $mdOut .= "### Usage\n```text\n{$help}\n```\n\n";
            }
            $mdOut .= "---\n\n";

            // UNIX roff page
            $safeName = str_replace(':', '-', $name);
            $date = date('d F Y');
            $roff = ".TH SPP-{$safeName} 1 \"{$date}\" \"SPP Framework\" \"SPP CLI Manual\"\n";
            $roff .= ".SH NAME\n";
            $roff .= "spp-{$safeName} \\- {$desc}\n";
            $roff .= ".SH SYNOPSIS\n";
            $roff .= ".B php spp.php {$name}\n";
            $roff .= "[OPTIONS]\n";
            $roff .= ".SH DESCRIPTION\n";
            if ($help) {
                $escapedHelp = str_replace("\n", "\n.br\n", $help);
                $roff .= $escapedHelp . "\n";
            } else {
                $roff .= "{$desc}\n";
            }
            
            file_put_contents($manDir . "/spp-{$safeName}.1", $roff);
        }

        file_put_contents($docsDir . '/spp-cli-manual.md', $mdOut);

        echo "Generated documentation successfully!\n";
        echo "- Markdown manual saved to: $docsDir/spp-cli-manual.md\n";
        echo "- UNIX man pages saved to: $manDir/spp-*.1\n";
    }
}
