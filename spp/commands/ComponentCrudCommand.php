<?php
namespace SPP\CLI\Commands;

use SPP\App;

class ComponentCrudCommand extends BaseElementCommand
{
    protected string $name = 'component:crud';
    protected string $description = 'Manage SPP UI components (list, create, edit, delete)';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $this->handleCrud('component', $args);
    }

    protected function getElementPath(string $type, string $name, string $appname): string
    {
        $app = App::getApp($appname);
        return $app->getAppSrcDir() . '/views/components/' . $name . '.edge';
    }

    protected function listElements(string $type, string $appname): array
    {
        $app = App::getApp($appname);
        $dir = $app->getAppSrcDir() . '/views/components';
        $elements = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.edge') as $file) {
                $elements[] = basename($file, '.edge');
            }
        }
        return $elements;
    }
}
