<?php
namespace SPPMod\Lekhak\Filters;

use SPPMod\Lekhak\Core\FilterInterface;

/**
 * Class I18nFilter
 * Handles language detection and polyglot translation tags.
 */
class I18nFilter implements FilterInterface
{
    protected string $lang = 'en';
    protected array $translations = [];

    public function getPriority(): int
    {
        return 5; // Run very early
    }

    public function preProcess(string &$content, array &$context): void
    {
        $this->detectLanguage();
        $this->loadTranslations();

        // Resolve Polyglot Tags
        // Twig: {{ 'key'|t }}
        $content = preg_replace_callback('/{{\s*[\'"](.*?)[\'"]\s*\|\s*t\s*}}/', function($m) {
            return $this->translate($m[1]);
        }, $content);

        // Blade: @t('key')
        $content = preg_replace_callback('/@t\([\'"](.*?)[\'"]\)/', function($m) {
            return $this->translate($m[1]);
        }, $content);
    }

    public function postProcess(string &$output, array &$context): void
    {
        // Add lang attribute to html tag if possible
        $output = preg_replace('/<html(.*?)>/', "<html$1 lang='{$this->lang}'>", $output);
    }

    protected function detectLanguage(): void
    {
        // Logic to detect language from URL (/hi/...) or session
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $parts = explode('/', trim($uri, '/'));
        if (isset($parts[0]) && strlen($parts[0]) === 2) {
            $this->lang = $parts[0];
        }
    }

    protected function loadTranslations(): void
    {
        $app = \SPP\Scheduler::getContext();
        $path = SPP_APP_DIR . "/etc/apps/$app/i18n/{$this->lang}.yml";
        if (file_exists($path)) {
            $this->translations = \Symfony\Component\Yaml\Yaml::parseFile($path) ?: [];
        }
    }

    protected function translate(string $key): string
    {
        return $this->translations[$key] ?? $key;
    }
}
