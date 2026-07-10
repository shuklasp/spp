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
        $this->engine->addFunction(new TwigFunction('spppartial', ['\\SPPMod\\Drishyam\\TemplateMacros', 'spppartial'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('polyglotpartial', ['\\SPPMod\\Drishyam\\TemplateMacros', 'polyglotpartial'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('polyglot', ['\\SPPMod\\Drishyam\\TemplateMacros', 'polyglot'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('spptransform', ['\\SPPMod\\Drishyam\\TemplateMacros', 'spptransform'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppuntransform', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppuntransform'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('sppcompose', ['\\SPPMod\\Drishyam\\TemplateMacros', 'sppcompose'], $safeHtml));
        $this->engine->addFunction(new TwigFunction('spplivepartial', ['\\SPPMod\\Drishyam\\TemplateMacros', 'spplivepartial'], $safeHtml));
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
}
