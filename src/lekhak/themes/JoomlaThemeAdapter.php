<?php
namespace App\Lekhak\Themes;

use SPP\Theme\ThemeAdapterInterface;

/**
 * JoomlaThemeAdapter
 *
 * Loads and renders templates from a Joomla template directory structure.
 * Expects templates to follow the standard Joomla layout:
 *   templates/<template-name>/templateDetails.xml
 *   templates/<template-name>/index.php
 *   templates/<template-name>/css/template.css
 *   templates/<template-name>/html/         (override folder)
 *
 * Like the WP adapter, this does NOT bootstrap Joomla's framework —
 * it only renders the template files with context variables.
 */
class JoomlaThemeAdapter implements ThemeAdapterInterface
{
    /** @var string Absolute path to the active Joomla template directory */
    private string $templatePath;

    /** @var string Template name */
    private string $templateName;

    /** @var array Parsed templateDetails.xml metadata */
    private array $metadata = [];

    public function __construct(string $templatePath = '')
    {
        if ($templatePath) {
            $this->templatePath = rtrim($templatePath, '/\\');
        } else {
            $this->templatePath = $this->resolveDefaultPath();
        }
        $this->templateName = basename($this->templatePath);
        $this->loadMetadata();
    }

    public function loadTemplate(string $name): string
    {
        $file = $this->resolveTemplateFile($name);
        if (!$file || !file_exists($file)) {
            return "<!-- JoomlaThemeAdapter: template '{$name}' not found in {$this->templateName} -->";
        }
        return file_get_contents($file);
    }

    public function render(string $template, array $context = []): string
    {
        $file = $this->resolveTemplateFile($template);
        if (!$file || !file_exists($file)) {
            return "<!-- JoomlaThemeAdapter: template '{$template}' not found -->";
        }

        // Make context available as local variables
        extract($context, EXTR_SKIP);

        // Provide Joomla-style document object (simplified)
        $this_template = $this;

        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Get the stylesheet path for the Joomla template.
     */
    public function getStylesheetPath(): string
    {
        // Joomla convention: css/template.css
        $cssFile = $this->templatePath . '/css/template.css';
        return file_exists($cssFile) ? $cssFile : '';
    }

    /**
     * Get template metadata from templateDetails.xml.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    // ── Internal ───────────────────────────────────────────────────────

    /**
     * Joomla uses component/view-based overrides in the html/ folder.
     * Mapping: logical name → file path.
     */
    private function resolveTemplateFile(string $name): ?string
    {
        // 1. Check the html/ override folder (Joomla convention)
        //    e.g., 'com_content.article' => html/com_content/article/default.php
        $parts = explode('.', str_replace('--', '.', $name));
        if (count($parts) >= 2) {
            $overridePath = $this->templatePath . '/html/' . $parts[0] . '/' . $parts[1] . '/default.php';
            if (file_exists($overridePath)) return $overridePath;
        }

        // 2. Check for a direct file match
        $directFile = $this->templatePath . '/' . str_replace('--', '/', $name) . '.php';
        if (file_exists($directFile)) return $directFile;

        // 3. Fallback to index.php (Joomla's main template file)
        $fallback = $this->templatePath . '/index.php';
        return file_exists($fallback) ? $fallback : null;
    }

    private function loadMetadata(): void
    {
        $xmlFile = $this->templatePath . '/templateDetails.xml';
        if (!file_exists($xmlFile)) return;

        try {
            $xml = simplexml_load_file($xmlFile);
            if ($xml) {
                $this->metadata = [
                    'name'        => (string)($xml->name ?? $this->templateName),
                    'version'     => (string)($xml->version ?? '1.0'),
                    'author'      => (string)($xml->author ?? ''),
                    'description' => (string)($xml->description ?? ''),
                    'positions'   => [],
                ];
                // Parse module positions
                if (isset($xml->positions)) {
                    foreach ($xml->positions->position as $pos) {
                        $this->metadata['positions'][] = (string)$pos;
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently skip invalid XML
        }
    }

    private function resolveDefaultPath(): string
    {
        $base = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3);
        $joomlaBase = $base . '/src/lekhak/themes/templates';

        $activeTemplate = 'cassiopeia'; // Joomla 4+ default
        if (class_exists('\\SPP\\SPPConfig')) {
            $configured = \SPP\SPPConfig::get('joomla_template');
            if ($configured) $activeTemplate = $configured;
        }

        return $joomlaBase . '/' . $activeTemplate;
    }
}
