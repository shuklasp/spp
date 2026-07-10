<?php

namespace SPP\Core;

use SPP\Exceptions\EntityNotFoundException;

/**
 * Class ResourceController
 * Provides a base for RESTful resource management in SPP, fully independent of external modules.
 * Inherits enterprise ViewController capabilities dynamically without module coupling.
 *
 * AVAILABLE ENTERPRISE FEATURES & EXTERNAL PARTIAL REFERENCING:
 *   - #[Middleware(ClassName::class)] : Attach middleware via PHP 8 attributes at class or method level.
 *   - $this->validate(array $rules)   : Validate incoming requests using ViewValidator rules.
 *   - $this->hydrate(string $class)   : Dynamically hydrate DTOs or Entities using DataTransformers.
 *   - $this->share($key, $value)      : Share data across all rendered views in this controller.
 *   - $this->getCspNonce()            : Secure per-request Content Security Policy nonce generation.
 *   - $this->stream($view, $data)     : Real-time Turbo Streams / live view streaming.
 *   - $this->json($data, $status)     : Send standardized JSON responses instantly.
 *   - ViewComposer & ViewLocator      : Auto-inject data into views and cache view resolution paths.
 *   - External Partials / Streams     : Reference standalone .html, .php, or .js files instead of writing HTML literals
 *                                       to dynamically update or insert content at particular places in the main page.
 */
abstract class ResourceController extends \SPP\SPPObject
{
    protected string $entityClass;
    protected array $sharedData = [];
    protected array $middleware = [];
    protected ?string $cspNonce = null;

    public function __construct()
    {
        if (empty($this->entityClass)) {
            // Try to guess from controller name (e.g. UserController -> User)
            $className = (new \ReflectionClass($this))->getShortName();
            $entityName = str_replace('Controller', '', $className);
            $this->entityClass = "\\App\\Default\\Entities\\$entityName";
        }
    }

    protected function share(string $key, $value): self
    {
        $this->sharedData[$key] = $value;
        return $this;
    }

    protected function getShared(): array
    {
        return $this->sharedData;
    }

    public function getMiddleware(?string $method = null): array
    {
        $middlewares = $this->middleware;

        $refClass = new \ReflectionClass($this);
        if (class_exists('\\SPPMod\\SPPView\\Attributes\\Middleware')) {
            foreach ($refClass->getAttributes(\SPPMod\SPPView\Attributes\Middleware::class) as $attribute) {
                $instance = $attribute->newInstance();
                $middlewares[] = $instance->class;
            }

            if ($method && $refClass->hasMethod($method)) {
                $refMethod = $refClass->getMethod($method);
                foreach ($refMethod->getAttributes(\SPPMod\SPPView\Attributes\Middleware::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    $middlewares[] = $instance->class;
                }
            }
        }

        return array_unique($middlewares);
    }

    protected function getCspNonce(): string
    {
        if ($this->cspNonce === null) {
            $this->cspNonce = base64_encode(random_bytes(16));
            if (!headers_sent()) {
                header("Content-Security-Policy: script-src 'self' 'nonce-{$this->cspNonce}'; style-src 'self' 'nonce-{$this->cspNonce}';");
            }
        }
        return $this->cspNonce;
    }

    protected function validate(array $rules, ?array $data = null)
    {
        if (class_exists('\\SPPMod\\SPPView\\SPPRequest') && $data === null) {
            $request = new \SPPMod\SPPView\SPPRequest();
            $data = $request->getData();
        } elseif ($data === null) {
            $data = $_POST;
        }

        if (class_exists('\\SPPMod\\SPPView\\ViewValidator')) {
            $validator = new class extends \SPPMod\SPPView\ViewValidator {};
            $result = $validator->validateAll($data, $rules);

            if (!$result->isValid() && class_exists('\\SPPMod\\SPPView\\SPP_ViewErrors')) {
                foreach ($result->getErrors() as $field => $messages) {
                    foreach ($messages as $msg) {
                        \SPPMod\SPPView\SPP_ViewErrors::addError($field, $msg, 'error');
                        \SPPMod\SPPView\SPP_ViewErrors::addError('global', "{$field}: {$msg}", 'error');
                    }
                }
            }
            return $result;
        }

        // Dummy validation result fallback if SPPView is disabled
        return new class {
            public function isValid(): bool { return true; }
            public function getErrors(): array { return []; }
        };
    }

    protected function hydrate(string $dtoClass, ?array $data = null, array $transformers = []): object
    {
        if (class_exists('\\SPPMod\\SPPView\\SPPRequest') && $data === null) {
            $request = new \SPPMod\SPPView\SPPRequest();
            $data = $request->getData();
        } elseif ($data === null) {
            $data = $_POST;
        }

        $refClass = new \ReflectionClass($dtoClass);
        $instance = $refClass->newInstanceWithoutConstructor();

        foreach ($refClass->getProperties() as $property) {
            $name = $property->getName();
            if (array_key_exists($name, $data)) {
                $value = $data[$name];
                if (isset($transformers[$name]) && class_exists('\\SPPMod\\SPPView\\DataTransformer') && $transformers[$name] instanceof \SPPMod\SPPView\DataTransformer) {
                    $value = $transformers[$name]->reverseTransform($value);
                }
                $property->setAccessible(true);
                $property->setValue($instance, $value);
            }
         }

        return $instance;
    }

    protected function addJs(string $path, array $options = []): self
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewAssetManager')) {
            \SPPMod\SPPView\ViewAssetManager::addJsIncludeFile($path, $options);
        }
        return $this;
    }

    protected function addCss(string $path): self
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewAssetManager')) {
            \SPPMod\SPPView\ViewAssetManager::addCssIncludeFile($path);
        }
        return $this;
    }

    protected function addJsContent(string $content): self
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewAssetManager')) {
            \SPPMod\SPPView\ViewAssetManager::addJsContent($content);
        }
        return $this;
    }

    protected function addCssContent(string $content): self
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewAssetManager')) {
            \SPPMod\SPPView\ViewAssetManager::addCssContent($content);
        }
        return $this;
    }

    protected function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    protected function isHtmx(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    protected function beforeRender(string $view, array &$data): void {}
    protected function afterRender(string $view, string &$output): void {}

    protected function render(string $view, array $data = [], ?string $engine = null, ?string $cacheKey = null, int $ttl = 3600): string
    {
        if ($cacheKey && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            $cached = \SPPMod\SPPCache\SPPCacheManager::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $app = \SPP\Scheduler::getContext();
        $data = array_merge($this->sharedData, $data);

        if ($this->cspNonce !== null && !isset($data['csp_nonce'])) {
            $data['csp_nonce'] = $this->cspNonce;
        }

        if (!isset($data['app_name'])) {
            $data['app_name'] = $app;
        }
        if (!isset($data['base_url'])) {
            $data['base_url'] = \SPP\App::getBaseUrl($app);
        }
        if (!isset($data['e'])) {
            $data['e'] = fn($str) => htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
        }

        if (class_exists('\\SPPMod\\SPPView\\ViewComposer')) {
            \SPPMod\SPPView\ViewComposer::compose($view, $data);
        }

        $this->beforeRender($view, $data);

        $params = new \SPP\EventParams([
            'view' => $view,
            'data' => $data,
            'engine' => $engine,
            'controller' => $this,
            'app' => $app,
            'handled' => false,
            'output' => ''
        ]);

        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent('spp.controller.render', $params);
        }

        if ($params->get('handled')) {
            $output = $params->get('output');
        } else {
            if (class_exists('\\SPPMod\\SPPView\\ViewLocator')) {
                $file = \SPPMod\SPPView\ViewLocator::locate($view, $app);
                if ($file) {
                    $output = self::renderIsolated($file, $data);
                } else {
                    throw new \Exception("View template '{$view}' not found for app '{$app}'.");
                }
            } else {
                throw new \Exception("SPPView module is not available to locate view '{$view}'.");
            }
        }

        $this->afterRender($view, $output);

        if ($this->cspNonce !== null && (strpos($output, '<script') !== false || strpos($output, '<style') !== false)) {
            $output = preg_replace('/<script(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<script$1 nonce="' . $this->cspNonce . '">', $output);
            $output = preg_replace('/<style(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<style$1 nonce="' . $this->cspNonce . '">', $output);
        }

        if ($cacheKey && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            \SPPMod\SPPCache\SPPCacheManager::set($cacheKey, $output, ['view', "view.{$app}.{$view}"]);
        }

        return $output;
    }

    protected function renderPartial(string $view, array $data = [], ?string $engine = null, ?string $cacheKey = null, int $ttl = 3600): string
    {
        $data['is_partial'] = true;
        return $this->render($view, $data, $engine, $cacheKey, $ttl);
    }

    protected function renderStaticPartial(string $view, int $status = 200): string
    {
        http_response_code($status);
        $app = \SPP\Scheduler::getContext();
        if (!class_exists('\\SPPMod\\SPPView\\ViewLocator')) {
            throw new \Exception("SPPView module is not available to locate static partial '{$view}'.");
        }
        $file = \SPPMod\SPPView\ViewLocator::locate($view, $app);

        if (!$file || !file_exists($file)) {
            throw new \Exception("Static partial view '{$view}' not found for app '{$app}'.");
        }

        $mtime = filemtime($file);
        $etag = '"' . md5($file . $mtime) . '"';

        if (!headers_sent()) {
            header('Cache-Control: public, max-age=3600');
            header('ETag: ' . $etag);
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
                http_response_code(304);
                exit;
            }
        }

        $output = file_get_contents($file);

        if ($this->cspNonce !== null && (strpos($output, '<script') !== false || strpos($output, '<style') !== false)) {
            $output = preg_replace('/<script(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<script$1 nonce="' . $this->cspNonce . '">', $output);
            $output = preg_replace('/<style(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<style$1 nonce="' . $this->cspNonce . '">', $output);
        }

        return $output;
    }

    protected function renderPolyglotPartial(string $lang, string $module, string $func, array $args = [], bool $daemon = false, int $status = 200, ?string $cacheKey = null, int $ttl = 3600): string
    {
        http_response_code($status);

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

        if ($this->cspNonce !== null && (strpos($output, '<script') !== false || strpos($output, '<style') !== false)) {
            $output = preg_replace('/<script(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<script$1 nonce="' . $this->cspNonce . '">', $output);
            $output = preg_replace('/<style(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<style$1 nonce="' . $this->cspNonce . '">', $output);
        }

        if ($cacheKey && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            $app = \SPP\Scheduler::getContext();
            \SPPMod\SPPCache\SPPCacheManager::set($cacheKey, $output, ['polyglot', "polyglot.{$app}.{$lang}"]);
        }

        return $output;
    }

    protected function polyglot(string $name, array $data = [], bool $useCache = true, int $ttl = 3600, int $status = 200): string
    {
        http_response_code($status);
        if (!class_exists('\\SPPMod\\Drishyam\\TemplateMacros')) {
            return "<div class=\"spp-partial-container error\"><pre>TemplateMacros class not found.</pre></div>";
        }

        $output = \SPPMod\Drishyam\TemplateMacros::polyglot($name, $data, $useCache, $ttl);

        if ($this->cspNonce !== null && (strpos($output, '<script') !== false || strpos($output, '<style') !== false)) {
            $output = preg_replace('/<script(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<script$1 nonce="' . $this->cspNonce . '">', $output);
            $output = preg_replace('/<style(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<style$1 nonce="' . $this->cspNonce . '">', $output);
        }

        return $output;
    }

    protected function streamPolyglot(string $langOrName, $moduleOrArgs = [], ?string $func = null, array $args = [], bool $daemon = true): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/vnd.turbo-stream.html; charset=utf-8');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        if (is_array($moduleOrArgs) && $func === null) {
            echo $this->polyglot($langOrName, $moduleOrArgs, false, 0, 200);
        } else {
            echo $this->renderPolyglotPartial($langOrName, is_string($moduleOrArgs) ? $moduleOrArgs : '', (string)$func, $args, $daemon, 200, null, 0);
        }
        flush();
    }

    protected function stream(string $view, array $data = [], ?string $engine = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/vnd.turbo-stream.html; charset=utf-8');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        $data['is_stream'] = true;
        echo $this->render($view, $data, $engine);
        flush();
    }

    protected function renderView(string $view, array $data = [], int $status = 200, ?string $engine = null): string
    {
        http_response_code($status);
        return $this->render($view, $data, $engine);
    }

    protected function json($data, int $status = 200): void
    {
        if (class_exists('\\SPP\\Response')) {
            \SPP\Response::json($data, $status);
        } else {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    protected function redirect(string $url, array $flashes = []): void
    {
        if (!empty($flashes) && class_exists('\\SPPMod\\SPPView\\SPPResponse')) {
            \SPPMod\SPPView\SPPResponse::redirect($url, $flashes);
        } elseif (class_exists('\\SPP\\Response')) {
            \SPP\Response::redirect($url);
        } else {
            header("Location: " . $url);
            exit;
        }
    }

    private static function renderIsolated(string $__file, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        include $__file;
        return ob_get_clean();
    }

    public function index($args)
    {
        $entities = ($this->entityClass)::find_all();
        $entityName = basename(str_replace('\\', '/', $this->entityClass));
        $data = ['items' => $entities, 'entityName' => $entityName];

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            $slug = strtolower($entityName);
            try {
                return $this->renderPartial("partials/{$slug}_index.html", $data);
            } catch (\Exception $e) {
                try {
                    return $this->renderPartial("partials/{$slug}_index.php", $data);
                } catch (\Exception $e2) {
                    // Fallback to default return array if partial template doesn't exist yet
                }
            }
        }

        return [
            'view' => 'index',
            'data' => $data
        ];
    }

    public function store($args)
    {
        $data = $_POST;
        $entity = new $this->entityClass();
        $entity->setValues($data);
        $id = $entity->save();

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            $slug = strtolower(basename(str_replace('\\', '/', $this->entityClass)));
            try {
                return $this->renderPartial("partials/{$slug}_row.html", ['item' => $entity]);
            } catch (\Exception $e) {
                try {
                    return $this->renderPartial("partials/{$slug}_row.php", ['item' => $entity]);
                } catch (\Exception $e2) {
                    // Fallback to default return array
                }
            }
        }

        return [
            'success' => true,
            'id' => $id,
            'message' => 'Resource created successfully.'
        ];
    }

    public function show($id)
    {
        $entity = new $this->entityClass($id);
        $entityName = basename(str_replace('\\', '/', $this->entityClass));
        $data = ['item' => $entity, 'entityName' => $entityName];

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            $slug = strtolower($entityName);
            try {
                return $this->renderPartial("partials/{$slug}_show.html", $data);
            } catch (\Exception $e) {
                try {
                    return $this->renderPartial("partials/{$slug}_show.php", $data);
                } catch (\Exception $e2) {
                    // Fallback to default return array
                }
            }
        }

        return [
            'view' => 'show',
            'data' => $data
        ];
    }

    public function update($id, $args)
    {
        $data = $_POST;
        $entity = new $this->entityClass($id);
        $entity->setValues($data);
        $entity->save();

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            $slug = strtolower(basename(str_replace('\\', '/', $this->entityClass)));
            try {
                return $this->renderPartial("partials/{$slug}_row.html", ['item' => $entity, 'updated' => true]);
            } catch (\Exception $e) {
                try {
                    return $this->renderPartial("partials/{$slug}_row.php", ['item' => $entity, 'updated' => true]);
                } catch (\Exception $e2) {
                    // Fallback to default return array
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Resource updated successfully.'
        ];
    }

    public function destroy($id)
    {
        $db = \SPP\DB::getInstance();
        $entity = new $this->entityClass($id);
        
        if (method_exists($entity, 'delete')) {
            $entity->delete();
        } else {
            $table = method_exists($entity, 'getTable') ? $entity->getTable() : strtolower((new \ReflectionClass($entity))->getShortName()) . 's';
            $idField = method_exists($entity, 'getMetadata') ? $entity->getMetadata('id_field') : 'id';
            $sql = "DELETE FROM %tab% WHERE {$idField} = ?";
            $db->exec_squery($sql, $table, [$id]);
        }

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            $slug = strtolower(basename(str_replace('\\', '/', $this->entityClass)));
            try {
                return $this->renderPartial("partials/{$slug}_deleted.html", ['id' => $id]);
            } catch (\Exception $e) {
                return ''; // Standard HTMX pattern to remove element from DOM
            }
        }

        return [
            'success' => true,
            'message' => 'Resource deleted successfully.'
        ];
    }

    protected function renderPolyglotResourcePartial(string $lang, string $module, string $func, array $args = [], bool $daemon = false, ?string $cacheKey = null, int $ttl = 3600): string
    {
        if ($this->isHtmx() && !headers_sent()) {
            header('Vary: HX-Request');
        } elseif (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'] && !headers_sent()) {
            header('Vary: Turbo-Frame');
        }

        return $this->renderPolyglotPartial($lang, $module, $func, $args, $daemon, 200, $cacheKey, $ttl);
    }

    protected function renderPolyglotResource(string $name, array $data = [], bool $useCache = true, int $ttl = 3600): string
    {
        if ($this->isHtmx() && !headers_sent()) {
            header('Vary: HX-Request');
        } elseif (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'] && !headers_sent()) {
            header('Vary: Turbo-Frame');
        }

        return $this->polyglot($name, $data, $useCache, $ttl, 200);
    }

    /**
     * Attempt a workflow transition on an entity and automatically serve the corresponding HTMX external partial,
     * real-time Turbo Stream update, or standard JSON/redirect response based on request headers.
     *
     * @param object $entity The entity to transition
     * @param string $transitionName The target workflow status / transition name
     * @param array $contextData Additional context data for the transition
     * @param string|null $successView Optional specific external partial or stream template to render on success
     * @param string|null $errorView Optional specific external partial or stream template to render on error
     * @return mixed
     */
    protected function transitionEntity(object $entity, string $transitionName, array $contextData = [], ?string $successView = null, ?string $errorView = null)
    {
        $success = false;
        $errorMsg = 'Workflow transition failed.';
        try {
            if (method_exists($entity, 'applyTransition')) {
                $success = $entity->applyTransition($transitionName, null, $contextData['comment'] ?? '', $contextData);
            } elseif (class_exists('\\SPPMod\\SPPWorkflow\\SPPWorkflowManager')) {
                $success = \SPPMod\SPPWorkflow\SPPWorkflowManager::applyTransition($entity, $transitionName, null, $contextData['comment'] ?? '', $contextData);
            } else {
                $statusField = method_exists($entity, 'getWorkflowStatusField') ? $entity->getWorkflowStatusField() : 'status';
                if (method_exists($entity, 'set')) {
                    $entity->set($statusField, $transitionName);
                } elseif (property_exists($entity, $statusField) || isset($entity->$statusField)) {
                    $entity->$statusField = $transitionName;
                }
                if (method_exists($entity, 'save')) {
                    $success = $entity->save();
                } else {
                    $success = true;
                }
            }
        } catch (\Exception $e) {
            $success = false;
            $errorMsg = $e->getMessage();
        }

        $isTurboStream = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/vnd.turbo-stream.html') !== false;
        $isHtmx = $this->isHtmx();

        $data = array_merge($contextData, ['entity' => $entity, 'transition' => $transitionName, 'success' => $success, 'error' => $errorMsg]);

        if ($success) {
            if ($isTurboStream && $successView) {
                $this->stream($successView, $data);
                return;
            } elseif ($isHtmx && $successView) {
                return $this->renderPartial($successView, $data);
            }
            return ['success' => true, 'transition' => $transitionName, 'entity' => $entity];
        } else {
            if ($isTurboStream && $errorView) {
                $this->stream($errorView, $data);
                return;
            } elseif ($isHtmx && $errorView) {
                http_response_code(422);
                return $this->renderPartial($errorView, $data);
            }
            http_response_code(422);
            return ['success' => false, 'error' => $errorMsg];
        }
    }
}
