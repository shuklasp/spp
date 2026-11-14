<?php

namespace SPPMod\Drishyam;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Class SPPTwig
 * Wrapper for Twig engine in SPP.
 */
class SPPTwig extends \SPP\SPPObject
{
    protected ?Environment $engine = null;
    protected string $viewsPath;
    protected string $cachePath;
    protected static ?self $instance = null;

    public function __construct()
    {
        $app = \SPP\App::getApp();
        $appName = $app->getName();

        // Resolve paths relative to app source or base directory
        $srcDir = $app->getAppSrcDir();
        if (is_dir($srcDir . '/resources/views')) {
            $this->viewsPath = $srcDir . '/resources/views';
        } else {
            $this->viewsPath = SPP_APP_DIR . '/resources/' . $appName . '/views';
        }

        $this->cachePath = SPP_APP_DIR . '/var/cache/' . $appName . '/twig';

        $this->ensureDirectories();

        $loader = new FilesystemLoader($this->viewsPath);
        
        $this->engine = new Environment($loader, [
            'cache' => $this->cachePath,
            'debug' => true, // Force debug for live CSS/layout loading in SPP-UX
            'auto_reload' => true,
        ]);

        $this->registerDirectives();
    }

    /**
     * Register custom SPP Twig functions using the unified TemplateMacros.
     */
    protected function registerDirectives(): void
    {
        $safeHtml = ['is_safe' => ['html']];

        $this->engine->addFunction(new TwigFunction('module_component', ['\\SPPMod\\Drishyam\\TemplateMacros', 'module_component'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppform', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppform'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppform_start', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppform_start'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppform_end', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppform_end'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppelement', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppelement'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppauth', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppauth']));
        $this->engine->addFunction(new TwigFunction('sppbind', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppbind'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('react', ['\\SPPMod\\Drishyam\\TemplateMacros', 'react'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('vue', ['\\SPPMod\\Drishyam\\TemplateMacros', 'vue'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppux', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppux'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppwidget', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppwidget'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('spptable', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('table', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppfilter', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('filter-builder', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('spptimeline', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('timeline', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppmetricgrid', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('metric-grid', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppbadge', function($labelOrProps = [], $options = []) {
            if (is_string($labelOrProps)) {
                $props = array_merge(['label' => $labelOrProps], is_array($options) ? $options : []);
            } else {
                $props = is_array($labelOrProps) ? $labelOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('badge', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppdiff', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('diff', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('spptransfer', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('transfer', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppwizard', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('wizard', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppcode', function($codeOrProps = [], $options = []) {
            if (is_string($codeOrProps)) {
                $props = array_merge(['code' => $codeOrProps], is_array($options) ? $options : []);
            } else {
                $props = is_array($codeOrProps) ? $codeOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('code-block', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppbreadcrumb', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('breadcrumb', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppdropdown', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('dropdown', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppempty', function($titleOrProps = [], $options = []) {
            if (is_string($titleOrProps)) {
                $props = array_merge(['title' => $titleOrProps], is_array($options) ? $options : []);
            } else {
                $props = is_array($titleOrProps) ? $titleOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('empty-state', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppfileupload', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('file-upload', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppdaterange', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('daterange', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('spptabs', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('tabs', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('spptree', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('tree', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppkanban', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('kanban', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppaccordion', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('accordion', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppcard', function($titleOrProps = [], $content = '') {
            if (is_string($titleOrProps)) {
                $props = ['title' => $titleOrProps, 'content' => $content];
            } else {
                $props = is_array($titleOrProps) ? $titleOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('card', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('spplist', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('list', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppdescriptionlist', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('description-list', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppavatar', function($nameOrProps = [], $options = []) {
            if (is_string($nameOrProps)) {
                $props = array_merge(['name' => $nameOrProps], is_array($options) ? $options : []);
            } else {
                $props = is_array($nameOrProps) ? $nameOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('avatar', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppkbd', function($keysOrProps = []) {
            if (is_string($keysOrProps)) {
                $props = ['keys' => $keysOrProps];
            } else {
                $props = is_array($keysOrProps) ? $keysOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('kbd', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('spprating', function($valueOrProps = [], $max = 5) {
            if (is_numeric($valueOrProps)) {
                $props = ['value' => $valueOrProps, 'max' => $max];
            } else {
                $props = is_array($valueOrProps) ? $valueOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('rating', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppslider', function($valueOrProps = [], $min = 0, $max = 100) {
            if (is_numeric($valueOrProps)) {
                $props = ['value' => $valueOrProps, 'min' => $min, 'max' => $max];
            } else {
                $props = is_array($valueOrProps) ? $valueOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('slider', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppcolorpicker', function($colorOrProps = []) {
            if (is_string($colorOrProps)) {
                $props = ['value' => $colorOrProps];
            } else {
                $props = is_array($colorOrProps) ? $colorOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('color-picker', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppswitch', function($labelOrProps = [], $checked = false) {
            if (is_string($labelOrProps)) {
                $props = ['label' => $labelOrProps, 'checked' => $checked];
            } else {
                $props = is_array($labelOrProps) ? $labelOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('switch', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppmarkdowneditor', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('markdown-editor', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppsignature', function($props = []) { return \SPPMod\Drishyam\TemplateMacros::sppwidget('signature', $props); }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('spppagination', function($pageOrProps = 1, $totalPages = 1) {
            if (is_numeric($pageOrProps)) {
                $props = ['page' => $pageOrProps, 'totalPages' => $totalPages];
            } else {
                $props = is_array($pageOrProps) ? $pageOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('pagination', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppstepper', function($props = [], $current = 1) {
            if (is_array($props) && isset($props[0])) {
                $props = ['steps' => $props, 'current' => $current];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('stepper', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppmodal', function($titleOrProps = [], $content = '') {
            if (is_string($titleOrProps)) {
                $props = ['title' => $titleOrProps, 'content' => $content];
            } else {
                $props = is_array($titleOrProps) ? $titleOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('modal', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppdrawer', function($titleOrProps = [], $content = '') {
            if (is_string($titleOrProps)) {
                $props = ['title' => $titleOrProps, 'content' => $content];
            } else {
                $props = is_array($titleOrProps) ? $titleOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('drawer', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppalert', function($messageOrProps = [], $variant = 'info') {
            if (is_string($messageOrProps)) {
                $props = ['message' => $messageOrProps, 'variant' => $variant];
            } else {
                $props = is_array($messageOrProps) ? $messageOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('alert', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppprogress', function($valueOrProps = 0) {
            if (is_numeric($valueOrProps)) {
                $props = ['value' => $valueOrProps];
            } else {
                $props = is_array($valueOrProps) ? $valueOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('progress', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppskeleton', function($variantOrProps = 'text') {
            if (is_string($variantOrProps)) {
                $props = ['variant' => $variantOrProps];
            } else {
                $props = is_array($variantOrProps) ? $variantOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('skeleton', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppqrcode', function($dataOrProps = '') {
            if (is_string($dataOrProps)) {
                $props = ['data' => $dataOrProps];
            } else {
                $props = is_array($dataOrProps) ? $dataOrProps : [];
            }
            return \SPPMod\Drishyam\TemplateMacros::sppwidget('qrcode', $props);
        }, $safeHtml));
        $this->engine->addFunction(new TwigFunction('spppartial', ['\\SPPMod\\Drishyam\\TemplateMacros', 'spppartial'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('polyglotpartial', ['\\SPPMod\\Drishyam\\TemplateMacros', 'polyglotpartial'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('polyglot', ['\\SPPMod\\Drishyam\\TemplateMacros', 'polyglot'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('spptransform', ['\\SPPMod\\Drishyam\\TemplateMacros', 'spptransform'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppuntransform', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppuntransform'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppcompose', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppcompose'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('spplivepartial', ['\\SPPMod\\Drishyam\\TemplateMacros', 'spplivepartial'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('route', 'route'));
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function render(string $view, array $data = []): string
    {
        return self::getInstance()->renderInstance($view, $data);
    }

    public function renderInstance(string $view, array $data = []): string
    {
        // Twig expects view names with extensions typically, but to match Blade's API:
        $viewFile = str_replace('.', '/', $view) . '.twig';
        return $this->engine->render($viewFile, $data);
    }

    protected function ensureDirectories(): void
    {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function getEngine(): Environment
    {
        return $this->engine;
    }

    /**
     * Alias for getEngine() returning Twig Environment.
     */
    public function getEnvironment(): Environment
    {
        return $this->engine;
    }

    /**
     * Render a raw Twig template string with data.
     */
    public function renderString(string $template, array $data = []): string
    {
        $tpl = $this->engine->createTemplate($template);
        return $tpl->render($data);
    }
}
