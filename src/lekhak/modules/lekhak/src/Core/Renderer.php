<?php
namespace SPPMod\Lekhak\Core;

/**
 * Class Renderer
 * The central orchestrator for the Lekhak Polyglot Pipeline.
 */
class Renderer
{
    protected array $filters = [];
    protected array $drivers = [];
    protected static ?Renderer $instance = null;

    public function __construct()
    {
        // Trigger event to allow other modules to register filters
        $params = ['renderer' => $this];
        \SPP\SPPEvent::fireEvent('lekhak_render_pipeline', $params);
        $this->sortFilters();
    }

    public static function getInstance(): Renderer
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add a filter to the pipeline.
     */
    public function addFilter(FilterInterface $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * Register a template driver (Blade, Twig, etc.)
     */
    public function registerDriver(string $type, callable $driver): void
    {
        $this->drivers[$type] = $driver;
    }

    /**
     * The main render method.
     */
    public function render(string $templateName, array $data = []): string
    {
        $output = null;
        
        // Try Drishyam Theming Engine first
        if (class_exists('\SPPMod\Drishyam\Drishyam')) {
            $drishyam = \SPPMod\Drishyam\Drishyam::getInstance();
            $drishyam->boot();
            
            // Resolve base data for all themes
            $data['app_context'] = \SPP\Scheduler::getContext();
            
            try {
                $output = \SPPMod\Drishyam\Drishyam::render($templateName, $data);
            } catch (\Exception $e) {
                // Fallback to native logic if theme-specific render fails
                @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[".date('Y-m-d H:i:s')."] DRISHYAM ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        if ($output === null) {
            $app = \SPP\App::getApp();
            $srcDir = $app->getAppSrcDir();
            $viewsDir = $srcDir . '/resources/views';
            
            $templatePath = $templateName;
            if (!file_exists($templatePath)) {
                $viewName = ltrim($templateName, '/');
                $candidatePaths = [
                    $viewsDir . '/' . $viewName . '.blade.php',
                    $viewsDir . '/admin/' . $viewName . '.blade.php',
                    $srcDir . '/resources/themes/lekhak_themes/glass_admin/views/' . $viewName . '.blade.php',
                    $srcDir . '/resources/themes/glass_admin/views/' . $viewName . '.blade.php',
                    $viewsDir . '/' . $viewName,
                    $viewsDir . '/admin/' . $viewName,
                ];

                foreach ($candidatePaths as $testPath) {
                    if (file_exists($testPath)) {
                        $templatePath = $testPath;
                        break;
                    }
                }
            }

            if (!file_exists($templatePath)) {
                 @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[".date('Y-m-d H:i:s')."] RENDERER ERROR: Template not found: {$templateName}\n", FILE_APPEND);
                 return "Template not found: {$templateName}";
            }

            $content = file_get_contents($templatePath);
            $type = pathinfo($templatePath, PATHINFO_EXTENSION);
            if (str_ends_with($templatePath, '.blade.php')) {
                $type = 'blade';
            }

            $context = [
                'path' => $templatePath,
                'type' => $type,
                'data' => $data
            ];

            // 1. Pre-Processing
            foreach ($this->filters as $filter) {
                $filter->preProcess($content, $context);
            }

            // 2. Logic Execution (Polyglot Dispatch)
            $output = $this->dispatchRendering($content, $context);

            // 3. Post-Processing
            foreach ($this->filters as $filter) {
                $filter->postProcess($output, $context);
            }
        } else {
            // Run post-processing on Drishyam's rendered output
            $context = [
                'path' => $templateName,
                'type' => 'drishyam',
                'data' => $data
            ];
            foreach ($this->filters as $filter) {
                $filter->postProcess($output, $context);
            }
        }

        // Bridge site-wide themes using event_spp_view_render_theme to guarantee dynamic runtime theme wrappers apply
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        $isAdmin = str_contains($reqUri, '/admin') || (str_contains($templateName, 'admin') && !str_contains($templateName, 'landing-page'));
        if (!$isAdmin) {
            $app = \SPP\App::getApp();
            $renderParams = [
                'html'     => &$output,
                'pageData' => $data,
                'theme'    => $app ? $app->getAppConf('theme') : 'eduxpro'
            ];
            \SPP\SPPEvent::fireEvent('event_spp_view_render_theme', $renderParams);
            $output = $renderParams['html'];
        }

        return $output;
    }

    protected function dispatchRendering(string $content, array $context): string
    {
        $type = $context['type'];
        
        // If we have a specific driver for this type, use it
        if (isset($this->drivers[$type])) {
            $result = ($this->drivers[$type])($content, $context['data'], $context);
            if ($result !== null) {
                return $result;
            }
        }

        // Fallback: Check for Blade (Native)
        if ($type === 'blade' || $type === 'php') {
            return $this->renderPhp($content, $context['data']);
        }

        return $content;
    }

    /**
     * Simple native PHP/Blade renderer (Zero Dependency fallback)
     */
    protected function renderPhp(string $content, array $data): string
    {
        extract($data);
        ob_start();
        
        // We write the pre-processed content to a temporary file to include it
        // In a real high-perf scenario, we'd use a cache directory
        $tmpFile = tempnam(sys_get_temp_dir(), 'lekhak_');
        file_put_contents($tmpFile, $content);
        
        try {
            include $tmpFile;
        } finally {
            unlink($tmpFile);
        }

        return ob_get_clean();
    }

    protected function sortFilters(): void
    {
        usort($this->filters, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
    }
}
