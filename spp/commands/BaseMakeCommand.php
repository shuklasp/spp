<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Abstract Class BaseMakeCommand
 * Provides common functionality for scaffolding commands.
 */
abstract class BaseMakeCommand extends Command
{
    public function isCLIOnly(): bool
    {
        return true;
    }

    /**
     * Resolve the target directory based on context.
     */
    protected function getTargetDir(string $subDir, string $app = 'default'): string
    {
        if ($app === 'default') {
            return SPP_APP_DIR . "/spp/{$subDir}";
        }
        
        // Ensure components are always in the src/ directory, not resources/
        if ($subDir === 'comp') {
            return SPP_APP_DIR . "/src/{$app}/comp";
        }

        return SPP_APP_DIR . "/src/{$app}/{$subDir}";
    }

    /**
     * Resolve the namespace based on context.
     */
    protected function getNamespace(string $subNamespace, string $app = 'default'): string
    {
        if ($app === 'default') {
            return "SPP\\" . ucfirst($subNamespace);
        }
        return "App\\" . ucfirst($app) . "\\" . ucfirst($subNamespace);
    }

    /**
     * Build the file from a stub and automatically register its properties in __spp_file_props.yml.
     */
    protected function buildFromStub(string $stubName, string $targetPath, array $replacements, array|string $props = []): bool
    {
        $stubPath = __DIR__ . "/stubs/{$stubName}.stub";
        if (!file_exists($stubPath)) {
            echo "Error: Stub '{$stubName}' not found.\n";
            return false;
        }

        $content = file_get_contents($stubPath);
        foreach ($replacements as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
        }

        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (file_exists($targetPath)) {
            echo "Error: File already exists at {$targetPath}\n";
            return false;
        }

        file_put_contents($targetPath, $content);

        // Automatically create or update directory manifest (__spp_file_props.yml)
        $this->recordFileProps($targetPath, $props);

        return true;
    }

    /**
     * Records or updates a file's metadata in the parent directory's __spp_file_props.yml manifest.
     */
    protected function recordFileProps(string $targetPath, array|string $props = []): bool
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once defined('SPP_APP_DIR') ? SPP_APP_DIR . '/spp/modules/spp/sppstorage/src/FilePropsManager.php' : dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }

        $dir = dirname($targetPath);
        $manifestPath = $dir . DIRECTORY_SEPARATOR . \SPPMod\SPPStorage\FilePropsManager::MANIFEST_FILENAME;
        if (!file_exists($manifestPath)) {
            \SPPMod\SPPStorage\FilePropsManager::saveDirectoryProps($dir, [
                'description' => 'Directory: ' . basename($dir),
                'usage' => '',
                'attributes' => ['tags' => [strtolower(basename($dir))]]
            ]);
        }

        if (empty($props)) {
            $name = basename($targetPath);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $props = [
                'description' => "Scaffolded component: {$name}",
                'usage' => "Generated via SPP CLI scaffolding",
                'stability' => 'stable',
                'author' => 'SPP Scaffolder',
                'review_date' => date('Y-m-d'),
                'attributes' => ['tags' => ['scaffold', $ext]]
            ];
        }

        $res = \SPPMod\SPPStorage\FilePropsManager::saveFileProps($targetPath, $props);
        return $res['success'] ?? false;
    }

    /**
     * Records or updates a directory's metadata in its __spp_file_props.yml manifest.
     */
    protected function recordDirectoryProps(string $dirPath, array $props = []): bool
    {
        if (!class_exists('\SPPMod\SPPStorage\FilePropsManager')) {
            require_once defined('SPP_APP_DIR') ? SPP_APP_DIR . '/spp/modules/spp/sppstorage/src/FilePropsManager.php' : dirname(__DIR__, 2) . '/modules/spp/sppstorage/src/FilePropsManager.php';
        }

        if (empty($props)) {
            $name = basename($dirPath);
            $props = [
                'description' => "Directory: {$name}",
                'usage' => '',
                'attributes' => ['tags' => [strtolower($name)]]
            ];
        }

        $res = \SPPMod\SPPStorage\FilePropsManager::saveDirectoryProps($dirPath, $props);
        return $res['success'] ?? false;
    }

    /**
     * Helper to get application context from arguments.
     */
    protected function getContext(array $args): string
    {
        foreach ($args as $arg) {
            if (strpos($arg, '--app=') === 0) {
                return substr($arg, 6);
            }
        }
        return \SPP\Scheduler::getContext() ?: 'default';
    }
}
