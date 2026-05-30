<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewServiceListCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $services = \SPPMod\SPPAjax\SPPAjax::listServices();
                $rows = [];
                foreach ($services as $s) {
                    $rows[] = [$s['name'], $s['script'], $s['method'] ?? 'POST'];
                }
                printTable(['Name', 'Script', 'Method'], $rows);
    }

    public function getName(): string
    {
        return 'view:service:list';
    }

    public function getDescription(): string
    {
        return 'Legacy port of view:service:list';
    }
}
