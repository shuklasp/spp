<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;
class ViewPageAddCommand extends Command {
    public function execute(array $args): void { echo "Legacy port missing logic.\n"; }
    public function getName(): string { return 'view:page:add'; }
    public function getDescription(): string { return 'Legacy port'; }
}
