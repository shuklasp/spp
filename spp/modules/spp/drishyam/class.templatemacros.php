<?php

namespace SPPMod\Drishyam;

/**
 * TemplateMacros
 * Single source of truth for runtime execution of template directives.
 * Used by both Blade and Twig engines.
 */
class TemplateMacros
{
    public static function toCssClasses(array|string $classes): string
    {
        if (is_string($classes)) {
            return $classes;
        }
        $classList = [];
        foreach ($classes as $key => $value) {
            if (is_numeric($key)) {
                $classList[] = $value;
            } elseif ($value) {
                $classList[] = $key;
            }
        }
        return implode(' ', array_filter($classList));
    }

    public static function module_component(string $viewStr, array $data = []): string
    {
        ob_start();
        if (strpos($viewStr, '::') !== false) {
            list($modName, $viewFile) = explode('::', $viewStr);
            $modClass = \SPP\ModuleCompiler::getActiveModuleClass($modName);
            if ($modClass) {
                $modInstance = \SPP\Module::getModule($modClass);
                if ($modInstance) {
                    $path = $modInstance->getModuleDir() . '/views/' . str_replace('.', '/', $viewFile) . '.blade.php';
                    if (file_exists($path)) {
                        // Currently hardcoded to Blade for module components, but could be dynamic
                        echo \SPPMod\Drishyam\SPPBlade::renderInstance($viewFile, $data);
                    }
                }
            }
        }
        return ob_get_clean() ?: '';
    }

    public static function sppform(string $fname): string
    {
        $appName = \SPP\Scheduler::getContext();
        $fname = str_replace(['\'', '"'], '', $fname);
        $app = \SPP\App::getApp($appName);
        $baseDir = $app->getAppConfDir() . '/forms/';

        $formFile = null;
        foreach (['yml', 'yaml', 'xml'] as $ext) {
            if (file_exists($baseDir . $fname . '.' . $ext)) {
                $formFile = $baseDir . $fname . '.' . $ext;
                break;
            }
        }

        if ($formFile) {
            \SPPMod\SPPView\ViewPage::readFormFile($formFile);
        }
        return '';
    }

    public static function sppform_start(string $fname): string
    {
        $forms = \SPPMod\SPPView\ViewPage::getFormsList();
        $fname = str_replace(['\'', '"'], '', $fname);
        if (isset($forms[$fname])) {
            $forms[$fname]->startForm();
        }
        return '';
    }

    public static function sppform_end(): string
    {
        $forms = \SPPMod\SPPView\ViewPage::getFormsList();
        $activeForm = end($forms);
        if ($activeForm) {
            $activeForm->endForm();
        }
        return '';
    }

    public static function sppelement(string $elemId, array $attrs = []): string
    {
        ob_start();
        $forms = \SPPMod\SPPView\ViewPage::getFormsList();
        foreach ($forms as $form) {
            $elements = $form->get('element');
            if (isset($elements[$elemId])) {
                $el = $elements[$elemId];
                if (!empty($attrs) && method_exists($el, 'setAttributes')) {
                    $el->setAttributes($attrs);
                }
                echo $el->getHTML();
                break;
            }
        }
        return ob_get_clean() ?: '';
    }

    public static function sppauth(): bool
    {
        return \SPPMod\SPPAuth\SPPAuth::authSessionExists();
    }

    public static function sppbind($entity): string
    {
        $forms = \SPPMod\SPPView\ViewPage::getFormsList();
        $activeForm = end($forms);
        if ($activeForm && isset($entity)) {
            $activeForm->bind($entity);
        }
        return '';
    }

    public static function react(string $name, array $props = []): string
    {
        $propsJson = json_encode($props);
        $context = \SPP\Scheduler::getContext();
        $app = \SPP\App::getApp($context);
        $srcPath = \SPP\App::getAppConf('src_path', $context) ?? ('src/' . $context);
        $path = "/{$srcPath}/resources/js/react/{$name}.js";
        return "<div data-spp-component='1' data-spp-type='react' data-spp-path='{$path}' data-spp-props='" . htmlspecialchars($propsJson, ENT_QUOTES, 'UTF-8') . "'></div>";
    }

    public static function vue(string $name, array $props = []): string
    {
        $propsJson = json_encode($props);
        $context = \SPP\Scheduler::getContext();
        $app = \SPP\App::getApp($context);
        $srcPath = \SPP\App::getAppConf('src_path', $context) ?? ('src/' . $context);
        $path = "/{$srcPath}/resources/js/vue/{$name}.js";
        return "<div data-spp-component='1' data-spp-type='vue' data-spp-path='{$path}' data-spp-props='" . htmlspecialchars($propsJson, ENT_QUOTES, 'UTF-8') . "'></div>";
    }

    public static function sppux(string $name, array $props = []): string
    {
        if (class_exists('\\SPPMod\\SPPUX\\SPPUX')) {
            // Forward compatibility if SPPUX ever moves to its own module
            return \SPPMod\Drishyam\SPPUX::component($name, $props);
        } else {
            $propsJson = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');
            $context = \SPP\Scheduler::getContext();
            $appBaseUri = defined('APP_BASE_URI') ? APP_BASE_URI : '';
            $path = rtrim($appBaseUri, '/') . "/src/{$context}/comp/{$name}.js";
            return "<div data-spp-component='1' data-spp-type='ux' data-spp-path='{$path}' data-spp-props='{$propsJson}'></div>";
        }
    }

    public static function spppartial(string $view, array $data = []): string
    {
        $app = class_exists('\\SPPMod\\Drishyam\\SPPBlade') ? \SPPMod\Drishyam\SPPBlade::getCurrentRenderingApp() : null;
        if (!$app) {
            $app = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : null;
        }
        $app = $app ?: 'default';
        $file = \SPPMod\SPPView\ViewLocator::locate($view, $app);
        
        if (!isset($data['base_url'])) {
            $data['base_url'] = \SPP\App::getBaseUrl($app);
        }

        if (class_exists('\\SPPMod\\Drishyam\\SPPBlade') && (($file && str_ends_with($file, '.blade.php')) || str_ends_with($view, '.blade.php'))) {
            return \SPPMod\Drishyam\SPPBlade::render($file ?: $view, $data, $app);
        }

        if ($file && file_exists($file)) {
            // Render isolated scope natively (for extreme performance .html/.php partials)
            extract($data, EXTR_SKIP);
            ob_start();
            include $file;
            return ob_get_clean() ?: '';
        }
        return "<!-- Partial not found: " . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . " -->";
    }

    public static function polyglotpartial(string $lang, string $module, string $func, array $args = [], bool $daemon = false, ?string $cacheKey = null, int $ttl = 3600): string
    {
        if ($cacheKey && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            $cached = \SPPMod\SPPCache\SPPCacheManager::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        if (!class_exists('\\SPP\\PolyglotBridge')) {
            return "<div class=\"spp-partial-container error\"><div class=\"partial-header\"><h4>Polyglot Partial Error</h4></div><div class=\"partial-body\"><pre>PolyglotBridge class not found.</pre></div></div>";
        }

        $res = \SPP\PolyglotBridge::call($lang, $module, $func, $args, $daemon);
        if (!$res['success']) {
            $error = htmlspecialchars($res['error'] ?? 'Unknown Polyglot Execution Error', ENT_QUOTES, 'UTF-8');
            return "<div class=\"spp-partial-container error\"><div class=\"partial-header\"><h4>Polyglot Partial Error ({$lang})</h4></div><div class=\"partial-body\"><pre>{$error}</pre></div></div>";
        }

        $output = is_array($res['data']) ? json_encode($res['data'], JSON_PRETTY_PRINT) : (string)$res['data'];

        if ($cacheKey && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            $app = \SPP\Scheduler::getContext();
            \SPPMod\SPPCache\SPPCacheManager::set($cacheKey, $output, ['polyglot', "polyglot.{$app}.{$lang}"]);
        }

        return $output;
    }

    public static function polyglot(string $name, array $data = [], bool $useCache = true, int $ttl = 3600, $fallback = null): string
    {
        $app = \SPP\App::getApp();
        $appName = $app->getName();

        $cacheKey = null;
        if ($useCache) {
            $cacheKey = "polyglot_magic_" . $appName . "_" . md5($name . json_encode($data));
            if (class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
                $cached = \SPPMod\SPPCache\SPPCacheManager::get($cacheKey);
                if ($cached !== false) {
                    return $cached;
                }
            }
        }

        // Auto-discover file and language
        $possibleDirs = [
            SPP_APP_DIR . '/resources/' . $appName . '/services/polyglot',
            SPP_APP_DIR . '/resources/' . $appName . '/services',
            $app->getAppSrcDir() . '/services/polyglot',
            $app->getAppSrcDir() . '/services',
            SPP_APP_DIR . '/resources/default/services/polyglot',
            SPP_APP_DIR . '/resources/default/services',
        ];

        $foundPath = null;
        $lang = null;
        $func = null;

        // Dynamic auto-discovery via LanguageRegistry
        foreach ($possibleDirs as $dir) {
            if (!is_dir($dir)) continue;
            $files = glob($dir . '/' . $name . '.*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $info = class_exists('\\SPP\\Core\\Polyglot\\LanguageRegistry') 
                            ? \SPP\Core\Polyglot\LanguageRegistry::resolveByPath($file) 
                            : null;
                        if ($info) {
                            $foundPath = $file;
                            $lang = $info['lang'];
                            $func = $info['func'];
                            break 2;
                        }
                    }
                }
            }
        }

        // Fallback to legacy extensions if LanguageRegistry did not find a match
        if (!$foundPath) {
            $extensions = [
                'py' => ['lang' => 'python', 'func' => 'render_partial'],
                'js' => ['lang' => 'node', 'func' => 'renderPartial'],
                'go' => ['lang' => 'go', 'func' => 'RenderPartial'],
                'java' => ['lang' => 'java', 'func' => 'renderPartial'],
                'cs' => ['lang' => 'dotnet', 'func' => 'RenderPartial'],
                'pl' => ['lang' => 'perl', 'func' => 'render_partial'],
            ];

            foreach ($possibleDirs as $dir) {
                foreach ($extensions as $ext => $info) {
                    if (file_exists($dir . '/' . $name . '.' . $ext)) {
                        $foundPath = $dir . '/' . $name . '.' . $ext;
                        $lang = $info['lang'];
                        $func = $info['func'];
                        break 2;
                    }
                }
            }
        }

        if (!$foundPath) {
            if ($fallback !== null) {
                if (is_callable($fallback)) return (string)call_user_func($fallback, $data);
                if (is_string($fallback) && (str_ends_with($fallback, '.blade.php') || str_ends_with($fallback, '.php') || str_ends_with($fallback, '.html'))) {
                    return self::spppartial($fallback, $data);
                }
                return (string)$fallback;
            }

            $alertHtml = "<div class=\"spp-partial-container error polyglot-alert\">";
            $alertHtml .= "<div class=\"partial-header\"><h4>Polyglot Magic Auto-Discovery Error</h4></div>";
            $alertHtml .= "<div class=\"partial-body\"><p>External polyglot partial service <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong> was not found.</p>";
            $alertHtml .= "<p>To generate it instantly, run the following CLI command:</p>";
            $alertHtml .= "<pre>php spp.php make:polyglot-partial " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . " --lang=python --app=" . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . "</pre>";
            $alertHtml .= "</div></div>";
            return $alertHtml;
        }

        $modulePath = $foundPath;

        if (!class_exists('\\SPP\\PolyglotBridge')) {
            if ($fallback !== null) return is_callable($fallback) ? (string)call_user_func($fallback, $data) : (string)$fallback;
            return "<div class=\"spp-partial-container error\"><div class=\"partial-header\"><h4>Polyglot Partial Error</h4></div><div class=\"partial-body\"><pre>PolyglotBridge class not found.</pre></div></div>";
        }

        // Auto-enable daemon mode for sub-millisecond execution
        $res = \SPP\PolyglotBridge::call($lang, $modulePath, $func, $data, true);
        if (!$res['success']) {
            if ($fallback !== null) {
                if (is_callable($fallback)) return (string)call_user_func($fallback, $data);
                if (is_string($fallback) && (str_ends_with($fallback, '.blade.php') || str_ends_with($fallback, '.php') || str_ends_with($fallback, '.html'))) {
                    return self::spppartial($fallback, $data);
                }
                return (string)$fallback;
            }
            $error = htmlspecialchars($res['error'] ?? 'Unknown Polyglot Execution Error', ENT_QUOTES, 'UTF-8');
            return "<div class=\"spp-partial-container error\"><div class=\"partial-header\"><h4>Polyglot Magic Error ({$lang})</h4></div><div class=\"partial-body\"><pre>{$error}</pre></div></div>";
        }

        $output = is_array($res['data']) ? json_encode($res['data'], JSON_PRETTY_PRINT) : (string)$res['data'];

        if ($cacheKey && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            \SPPMod\SPPCache\SPPCacheManager::set($cacheKey, $output, ['polyglot', "polyglot.{$appName}.{$lang}"]);
        }

        return $output;
    }

    /**
     * Evaluates an ad-hoc polyglot expression (e.g. Formula DSL, Python, Node) directly in templates.
     */
    public static function polyglot_eval(string $lang, string $expression, array $context = []): string
    {
        try {
            $result = \SPP\Polyglot::eval($lang, $expression, $context);
            return is_array($result) || is_object($result)
                ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : (string)$result;
        } catch (\Throwable $e) {
            return "<!-- [Polyglot Eval Error ({$lang})]: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . " -->";
        }
    }

    public static function spptransform(string $transformerClass, $value)
    {
        if (class_exists('\\SPPMod\\SPPView\\DataTransformer')) {
            if (!class_exists($transformerClass)) {
                $candidate = '\\SPPMod\\SPPView\\' . $transformerClass;
                if (class_exists($candidate)) {
                    $transformerClass = $candidate;
                }
            }
            if (class_exists($transformerClass)) {
                $inst = new $transformerClass();
                if ($inst instanceof \SPPMod\SPPView\DataTransformer) {
                    return $inst->transform($value);
                }
            }
        }
        return $value;
    }

    public static function sppuntransform(string $transformerClass, $value)
    {
        if (class_exists('\\SPPMod\\SPPView\\DataTransformer')) {
            if (!class_exists($transformerClass)) {
                $candidate = '\\SPPMod\\SPPView\\' . $transformerClass;
                if (class_exists($candidate)) {
                    $transformerClass = $candidate;
                }
            }
            if (class_exists($transformerClass)) {
                $inst = new $transformerClass();
                if ($inst instanceof \SPPMod\SPPView\DataTransformer) {
                    return $inst->reverseTransform($value);
                }
            }
        }
        return $value;
    }

    public static function sppcompose(string $view, array $data = []): string
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewComposer')) {
            \SPPMod\SPPView\ViewComposer::compose($view, $data);
        }
        return '';
    }

    public static function spplivepartial(string $view, array $data = []): string
    {
        $topic = $data['topic'] ?? 'default_topic';
        $content = self::spppartial($view, $data);
        return "<div data-spp-live-topic=\"" . htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') . "\">" . $content . "</div>";
    }

    public static function region(string $region, array $context = []): string
    {
        $blocks = $context['blocks'] ?? [];
        if (empty($blocks)) {
            $projectId = $context['project_id'] ?? ($context['projectId'] ?? ($_SESSION['sppdocs_current_project'] ?? 'spp'));
            if (class_exists('\\App\\SPPDocs\\Services\\BlockManager')) {
                $blocks = \App\SPPDocs\Services\BlockManager::getRegionBlocks($projectId, $region);
            }
        }
        return \SPPMod\SPPView\BlockManager::renderRegion($region, $blocks, $context);
    }
}

