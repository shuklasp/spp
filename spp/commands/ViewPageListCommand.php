<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewPageListCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $pages = \SPPMod\SPPView\Pages::listPages();
                $rows = [];
                foreach ($pages as $p) {
                    $rows[] = [$p['name'], $p['url']];
                }
                printTable(['Name', 'URL'], $rows);
    }

    public function getName(): string
    {
        return 'view:page:list';
    }

    public function getDescription(): string
    {
        return 'Legacy port of view:page:list';
    }
}
