<?php
namespace SPP\CLI\Commands;

use SPP\App;

class ServiceCrudCommand extends BaseElementCommand
{
    protected string $name = 'service:crud';
    protected string $description = 'Manage SPP services (list, create, edit, delete)';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $this->handleCrud('service', $args);
    }

    protected function getElementPath(string $type, string $name, string $appname): string
    {
        $app = App::getApp($appname);
        $className = ucfirst($name);
        return $app->getAppSrcDir() . '/services/class.' . strtolower($className) . '.php';
    }

    protected function listElements(string $type, string $appname): array
    {
        $app = App::getApp($appname);
        $dir = $app->getAppSrcDir() . '/services';
        $elements = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/class.*.php') as $file) {
                $basename = basename($file, '.php');
                $elements[] = substr($basename, 6); // remove 'class.'
            }
        }
        return $elements;
    }

    protected function createElementTemplate(string $type, string $name, string $path): void
    {
        // Override to use make:service stub logic if desired, or simple default
        $className = ucfirst($name);
        $app = App::getApp('default'); // simplistic namespace retrieval
        $namespace = "App\\Default\\Services"; 
        
        $content = "<?php\nnamespace {$namespace};\n\n";
        $content .= "class {$className}\n{\n";
        $content .= "    public function handle() {\n        // Your logic here\n    }\n";
        $content .= "}\n";
        
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($path, $content);
    }
}
