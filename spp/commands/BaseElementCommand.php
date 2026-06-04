<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Scheduler;

abstract class BaseElementCommand extends Command
{
    /**
     * Helper to get the target path for an element type in a given app context.
     * 
     * @param string $type The element type (e.g. 'service', 'component', 'form', 'entity')
     * @param string $name The name of the element
     * @param string $appname The app name (e.g. 'default')
     * @return string The absolute file path
     */
    abstract protected function getElementPath(string $type, string $name, string $appname): string;

    /**
     * Helper to list all available elements of a given type.
     * 
     * @param string $type The element type
     * @param string $appname The app name
     * @return array<string> List of element names
     */
    abstract protected function listElements(string $type, string $appname): array;

    /**
     * Base CRUD handler for element commands.
     * 
     * Usage: php spp.php <command> <action> [name] [--app=appname] [--editor=editor]
     */
    protected function handleCrud(string $type, array $args): void
    {
        $action = $args[2] ?? 'list'; // list, create, edit, delete
        $name = $args[3] ?? null;
        
        $appname = 'default';
        $editor = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) $appname = substr($arg, 6);
            if (str_starts_with($arg, '--editor=')) $editor = substr($arg, 9);
        }

        switch ($action) {
            case 'list':
                $elements = $this->listElements($type, $appname);
                if (empty($elements)) {
                    $this->info("No {$type}s found in app '{$appname}'.");
                } else {
                    $this->info("Available {$type}s in app '{$appname}':");
                    foreach ($elements as $el) {
                        echo "  - $el\n";
                    }
                }
                break;
                
            case 'create':
                if (!$name) {
                    $this->error("Name required. Usage: php spp.php {$this->getName()} create <name>");
                    return;
                }
                $path = $this->getElementPath($type, $name, $appname);
                if (file_exists($path)) {
                    $this->error("{$type} '{$name}' already exists at {$path}");
                    return;
                }
                $this->createElementTemplate($type, $name, $path);
                $this->info("Created {$type} '{$name}' at {$path}");
                
                // Prompt to edit immediately
                if (strtolower($this->prompt("Edit now? (y/n)", "n")) === 'y') {
                    $this->openEditor($path, $editor);
                }
                break;

            case 'edit':
                if (!$name) {
                    $this->error("Name required. Usage: php spp.php {$this->getName()} edit <name>");
                    return;
                }
                $path = $this->getElementPath($type, $name, $appname);
                if (!file_exists($path)) {
                    $this->error("{$type} '{$name}' does not exist at {$path}");
                    return;
                }
                $this->openEditor($path, $editor);
                break;

            case 'delete':
                if (!$name) {
                    $this->error("Name required. Usage: php spp.php {$this->getName()} delete <name>");
                    return;
                }
                $path = $this->getElementPath($type, $name, $appname);
                if (!file_exists($path)) {
                    $this->error("{$type} '{$name}' does not exist.");
                    return;
                }
                if (strtolower($this->prompt("Are you sure you want to delete {$type} '{$name}'? (y/n)", "n")) === 'y') {
                    unlink($path);
                    $this->info("Deleted {$type} '{$name}'.");
                }
                break;

            default:
                $this->error("Unknown action '{$action}'. Valid actions: list, create, edit, delete.");
                break;
        }
    }

    /**
     * Create the default template for the element.
     */
    protected function createElementTemplate(string $type, string $name, string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $content = "";
        
        if ($type === 'entity' && (str_ends_with($path, '.yml') || str_ends_with($path, '.yaml'))) {
            $tableName = $this->prompt("Enter table name for entity {$name}", strtolower($name));
            $content = "name: {$name}\ntable: {$tableName}\nfields:\n  id:\n    type: int\n    primary: true\n    auto_increment: true\n";
        } elseif ($type === 'form' && (str_ends_with($path, '.yml') || str_ends_with($path, '.yaml'))) {
            $publicName = $this->prompt("Enter public name for form {$name}", ucfirst($name));
            $content = "id: {$name}\npublic_name: \"{$publicName}\"\nfields:\n  example:\n    label: \"Example Field\"\n    type: text\n";
        } else {
            if (str_ends_with($path, '.php')) {
                $content = "<?php\n\n// SPP {$type} {$name}\n\n";
            } elseif (str_ends_with($path, '.yml') || str_ends_with($path, '.yaml')) {
                $content = "name: {$name}\n# Configuration for {$name}\n";
            } elseif (str_ends_with($path, '.js')) {
                $content = "/**\n * SPP-UX Component: {$name}\n */\n";
            }
        }

        file_put_contents($path, $content);
    }

    /**
     * Opens a file in an editor.
     */
    protected function openEditor(string $path, ?string $editor = null): void
    {
        if (!$editor) {
            $editors = ['code', 'notepad', 'nano', 'vim'];
            echo "\nAvailable editors: " . implode(', ', $editors) . "\n";
            $editor = $this->prompt("Enter editor name", "notepad");
        }
        
        if (!$editor) {
            $this->warn("No editor specified. Aborting edit.");
            return;
        }

        $cmd = escapeshellcmd($editor) . ' ' . escapeshellarg($path);
        
        $this->info("Opening {$path} with {$editor}...");
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Use system or popen
            system($cmd);
        } else {
            // For Unix interactive editors like nano/vim
            $descriptorSpec = [
                0 => ["file", "/dev/tty", "r"],
                1 => ["file", "/dev/tty", "w"],
                2 => ["file", "/dev/tty", "w"]
            ];
            $process = @proc_open($cmd, $descriptorSpec, $pipes);
            if (is_resource($process)) {
                proc_close($process);
            } else {
                system($cmd);
            }
        }
    }
}
