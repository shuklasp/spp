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
            return;
        }

        // Try Drupal native *.info.yml drop-in presentation adapter
        $infoFiles = glob($this->path . '/*.info.yml');
        if (!empty($infoFiles)) {
            $parsed = \Symfony\Component\Yaml\Yaml::parseFile($infoFiles[0]);
            $this->config = [
                'name' => $parsed['name'] ?? $this->name,
                'description' => $parsed['description'] ?? 'Unmodified custom Drupal template layer.',
                'type' => isset($parsed['package']) && str_contains(strtolower($parsed['package']), 'admin') ? 'admin' : 'site',
                'ENGINE_MODE' => 'drupal',
                'original_info' => $parsed
            ];
            return;
        }

        // Try WordPress native style.css drop-in metadata header block adapter
        $wpStyle = $this->path . '/style.css';
        if (file_exists($wpStyle)) {
            $content = file_get_contents($wpStyle, false, null, 0, 1024);
            $name = $this->name;
            $desc = "Native WordPress drop-in presentation engine.";
            if (preg_match('/Theme Name:\s*(.*)/i', $content, $matches)) {
                $name = trim($matches[1]);
            }
            if (preg_match('/Description:\s*(.*)/i', $content, $matches)) {
                $desc = trim($matches[1]);
            }
            $this->config = [
                'name' => $name,
                'description' => $desc,
                'type' => 'site',
                'ENGINE_MODE' => 'wordpress'
            ];
            return;
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
        
        // Support dynamic resolution for native Drupal and WordPress template adapters
        $engineMode = $this->getConfig('ENGINE_MODE');
        if ($engineMode === 'drupal') {
            $twigPath = rtrim($this->path, '/\\') . '/templates/' . ltrim($viewPath, '/\\') . '.html.twig';
            if (file_exists($twigPath)) return $twigPath;
        } elseif ($engineMode === 'wordpress') {
            $wpPath = rtrim($this->path, '/\\') . '/' . ltrim($viewPath, '/\\') . '.php';
            if (file_exists($wpPath)) return $wpPath;
            $wpIndex = rtrim($this->path, '/\\') . '/index.php';
            if (file_exists($wpIndex)) return $wpIndex;
        }

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
