<?php

namespace SPPMod\Drishyam;

/**
 * Class Theme
 * Represents a Drishyam theme instance.
 */
class Theme extends \SPP\SPPObject
{
    protected string $name;
    protected string $path;
    protected array $config = [];

    public function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = $path;
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $configFile = $this->path . '/theme.yml';
        if (file_exists($configFile)) {
            $this->config = \Symfony\Component\Yaml\Yaml::parseFile($configFile);
        }
    }

    public function getName(): string { return $this->name; }
    public function getPath(): string { return $this->path; }
    public function getConfig(string $key = null, $default = null)
    {
        if ($key === null) return $this->config;
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Resolve a template file path within the theme.
     */
    public function resolveTemplate(string $view): ?string
    {
        $viewPath = str_replace('.', '/', $view);
        $fullPath = rtrim($this->path, '/\\') . '/views/' . ltrim($viewPath, '/\\') . '.blade.php';
        
        // Try Blade
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // Try SPPUX (JS)
        $uxPath = rtrim($this->path, '/\\') . '/comp/' . ltrim($viewPath, '/\\') . '.sppux.js';
        if (file_exists($uxPath)) {
            return $uxPath;
        }

        // Fallback to core app views directory to prevent template duplication
        $app = \SPP\App::getApp();
        if ($app) {
            $corePath = $app->getAppSrcDir() . '/resources/views/' . ltrim($viewPath, '/\\') . '.blade.php';
            if (file_exists($corePath)) {
                return $corePath;
            }
        }

        return null;
    }
}
