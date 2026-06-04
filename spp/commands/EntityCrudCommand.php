<?php
namespace SPP\CLI\Commands;

use SPP\App;

class EntityCrudCommand extends BaseElementCommand
{
    protected string $name = 'entity:crud';
    protected string $description = 'Manage SPP entities (list, create, edit, delete)';

    public function execute(array $args): void
    {
        $this->handleCrud('entity', $args);
    }

    protected function getElementPath(string $type, string $name, string $appname): string
    {
        $app = App::getApp($appname);
        return $app->getAppConfDir() . '/entities/' . $name . '.yml';
    }

    protected function listElements(string $type, string $appname): array
    {
        $app = App::getApp($appname);
        $dir = $app->getAppConfDir() . '/entities';
        $elements = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.{yml,yaml,xml}', GLOB_BRACE) as $file) {
                $elements[] = basename($file);
            }
        }
        return $elements;
    }
}
