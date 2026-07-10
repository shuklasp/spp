<?php

namespace SPPMod\SPPView;

/**
 * Class ViewController
 * Standard base controller providing a unified view rendering workflow extensible via events.
 * Other rendering modules (Blade, Twig, SPP-UX, Native) inject their logic through events.
 */
abstract class ViewController
{
    /**
     * @var array Data shared across all views rendered by this controller.
     */
    protected array $sharedData = [];

    /**
     * @var array List of middleware classes attached to this controller.
     */
    protected array $middleware = [];

    /**
     * @var string|null Per-request Content Security Policy nonce.
     */
    protected ?string $cspNonce = null;

    /**
     * Share a piece of data across all views rendered by this controller.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    protected function share(string $key, $value): self
    {
        $this->sharedData[$key] = $value;
        return $this;
    }

    /**
     * Get all shared data.
     *
     * @return array
     */
    protected function getShared(): array
    {
        return $this->sharedData;
    }

    /**
     * Get all middleware defined on this controller via property or PHP 8 Attributes.
     *
     * @param string|null $method Optional specific method name to inspect for method-level middleware
     * @return array List of middleware class names
     */
    public function getMiddleware(?string $method = null): array
    {
        $middlewares = $this->middleware;

        $refClass = new \ReflectionClass($this);
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

        return array_unique($middlewares);
    }

    /**
     * Generate or retrieve a secure per-request Content Security Policy (CSP) nonce.
     * Sets the appropriate CSP header if not already sent.
     *
     * @return string
     */
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

    /**
     * Validate incoming request data against a set of rules using ViewValidator.
     * Automatically flashes errors to SPP_ViewErrors if validation fails.
     *
     * @param array $rules Map of field rules [field => ['required', 'email']]
     * @param array|null $data Data to validate (defaults to SPPRequest data or $_POST)
     * @return ValidationResult
     */
    protected function validate(array $rules, ?array $data = null): ValidationResult
    {
        if ($data === null) {
            $request = new SPPRequest();
            $data = $request->getData();
        }

        // Instantiate an anonymous ViewValidator to run validateAll
        $validator = new class extends ViewValidator {};
        $result = $validator->validateAll($data, $rules);

        if (!$result->isValid() && class_exists('\\SPPMod\\SPPView\\SPP_ViewErrors')) {
            foreach ($result->getErrors() as $field => $messages) {
                foreach ($messages as $msg) {
                    SPP_ViewErrors::addError($field, $msg, 'error');
                    SPP_ViewErrors::addError('global', "{$field}: {$msg}", 'error');
                }
            }
        }

        return $result;
    }

    /**
     * Hydrate a DTO or Entity class with request data, optionally applying DataTransformers.
     *
     * @param string $dtoClass Fully qualified class name to instantiate
     * @param array|null $data Input data array (defaults to SPPRequest data)
     * @param array<string, DataTransformer> $transformers Map of field name to DataTransformer instance
     * @return object Populated DTO/Entity instance
     */
    protected function hydrate(string $dtoClass, ?array $data = null, array $transformers = []): object
    {
        if ($data === null) {
            $request = new SPPRequest();
            $data = $request->getData();
        }

        $refClass = new \ReflectionClass($dtoClass);
        $instance = $refClass->newInstanceWithoutConstructor();

        foreach ($refClass->getProperties() as $property) {
            $name = $property->getName();
            if (array_key_exists($name, $data)) {
                $value = $data[$name];
                if (isset($transformers[$name]) && $transformers[$name] instanceof DataTransformer) {
                    $value = $transformers[$name]->reverseTransform($value);
                }
                $property->setAccessible(true);
                $property->setValue($instance, $value);
            }
         }

        return $instance;
    }

    /**
     * Enqueue a JavaScript file via ViewAssetManager.
     *
     * @param string $path
     * @param array $options
     * @return $this
     */
    protected function addJs(string $path, array $options = []): self
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewAssetManager')) {
            \SPPMod\SPPView\ViewAssetManager::addJsIncludeFile($path, $options);
        }
        return $this;
    }

    /**
     * Enqueue a CSS file via ViewAssetManager.
     *
     * @param string $path
     * @return $this
     */
    protected function addCss(string $path): self
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewAssetManager')) {
            \SPPMod\SPPView\ViewAssetManager::addCssIncludeFile($path);
        }
        return $this;
    }

    /**
     * Enqueue raw JavaScript content via ViewAssetManager.
     *
     * @param string $content
     * @return $this
     */
    protected function addJsContent(string $content): self
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewAssetManager')) {
            \SPPMod\SPPView\ViewAssetManager::addJsContent($content);
        }
        return $this;
    }

    /**
     * Enqueue raw CSS content via ViewAssetManager.
     *
     * @param string $content
     * @return $this
     */
    protected function addCssContent(string $content): self
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewAssetManager')) {
            \SPPMod\SPPView\ViewAssetManager::addCssContent($content);
        }
        return $this;
    }

    /**
     * Check if the current request is an Ajax (XMLHttpRequest) call.
     *
     * @return bool
     */
    protected function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if the current request is an HTMX request.
     *
     * @return bool
     */
    protected function isHtmx(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    /**
     * Lifecycle hook invoked immediately before view rendering.
     *
     * @param string $view Name or path of the view template
     * @param array $data Data array passed to the view
     */
    protected function beforeRender(string $view, array &$data): void
    {
        // Child controllers can override this to inspect or modify data
    }

    /**
     * Lifecycle hook invoked immediately after view rendering.
     *
     * @param string $view Name or path of the view template
     * @param string $output Rendered HTML output
     */
    protected function afterRender(string $view, string &$output): void
    {
        // Child controllers can override this to inspect or modify output
    }

    /**
     * Render a view template using the standard event-driven rendering workflow with caching and lifecycle hooks.
     *
     * @param string $view Name or path of the view template
     * @param array $data Data array to pass to the view
     * @param string|null $engine Optional specific rendering engine requested (e.g., 'blade', 'twig', 'native')
     * @param string|null $cacheKey Optional cache identifier to store/retrieve the rendered output
     * @param int $ttl Cache time-to-live in seconds
     * @return string Rendered HTML output
     * @throws ViewNotFoundException
     */
    protected function render(string $view, array $data = [], ?string $engine = null, ?string $cacheKey = null, int $ttl = 3600): string
    {
        if ($cacheKey && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            $cached = \SPPMod\SPPCache\SPPCacheManager::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $app = \SPP\Scheduler::getContext();
        
        // Merge shared data with specific view data
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

        // Execute global view composers
        if (class_exists('\\SPPMod\\SPPView\\ViewComposer')) {
            \SPPMod\SPPView\ViewComposer::compose($view, $data);
        }

        // Invoke pre-render lifecycle hook
        $this->beforeRender($view, $data);

        // 1. Fire event to allow rendering modules (Drishyam/SPPBlade, Twig, etc.) to inject their logic
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
            // 2. Fallback: Native PHP view rendering workflow via ViewLocator
            $file = ViewLocator::locate($view, $app);

            if ($file) {
                $output = self::renderIsolated($file, $data);
            } else {
                // 3. Throw exception if no view file found
                throw new ViewNotFoundException("View template '{$view}' not found for app '{$app}'.");
            }
        }

        // Invoke post-render lifecycle hook
        $this->afterRender($view, $output);

        // Automated CSP Nonce Injection in Partials & Turbo Streams
        if ($this->cspNonce !== null && (strpos($output, '<script') !== false || strpos($output, '<style') !== false)) {
            $output = preg_replace_callback('/<(script|style)([^>]*)>/i', function ($matches) {
                $tag = $matches[1];
                $attrs = $matches[2];
                if (stripos($attrs, 'nonce=') !== false) {
                    return $matches[0];
                }
                return "<{$tag}{$attrs} nonce=\"" . $this->cspNonce . "\">";
            }, $output);
        }

        if ($cacheKey && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            \SPPMod\SPPCache\SPPCacheManager::set($cacheKey, $output, ['view', "view.{$app}.{$view}"]);
        }

        return $output;
    }

    /**
     * Render a view partial (specifically for HTMX / Ajax / Component rendering).
     *
     * REFERENCING EXTERNAL PARTIALS (AVOIDING HTML LITERALS):
     * Instead of writing inline HTML string literals within controller actions or JS components,
     * you can pass external file paths (e.g. 'partials/user-card.html', 'partials/widget.php', 'comp/widget.js').
     * ViewLocator will automatically discover these files across custom and standard directories,
     * allowing HTMX or client-side components to cleanly insert or update them at particular places in the main page.
     *
     * @param string $view Name or path of the view template (supports explicit .html, .php, .js, .blade.php extensions)
     * @param array $data Data array to pass to the view
     * @param string|null $engine Optional specific rendering engine requested
     * @param string|null $cacheKey Optional cache identifier
     * @param int $ttl Cache time-to-live in seconds
     * @return string Rendered HTML output
     * @throws ViewNotFoundException
     */
    protected function renderPartial(string $view, array $data = [], ?string $engine = null, ?string $cacheKey = null, int $ttl = 3600): string
    {
        if (!headers_sent()) {
            header('View-Transition: same-origin');
        }
        $data['is_partial'] = true;
        $rendered = $this->render($view, $data, $engine, $cacheKey, $ttl);
        // Automatically inject view-transition-name attribute to outer element if entity id is present
        if (isset($data['id']) && !str_contains($rendered, 'view-transition-name')) {
            $transName = 'vt-' . preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace('/', '-', $view)) . '-' . $data['id'];
            $rendered = preg_replace('/^<([a-z0-9]+)([^>]*)>/is', '<$1$2 style="view-transition-name: ' . $transName . '">', $rendered, 1);
        }
        return $rendered;
    }

    /**
     * Render a static standalone partial template (specifically optimized for static .html or .js files).
     * Automatically outputs ETag and Cache-Control headers for ultra-fast client-side caching and response times.
     *
     * @param string $view Name or path of the view template
     * @param int $status HTTP response status code
     * @return string
     * @throws ViewNotFoundException
     */
    protected function renderStaticPartial(string $view, int $status = 200): string
    {
        http_response_code($status);
        $app = \SPP\Scheduler::getContext();
        $file = ViewLocator::locate($view, $app);

        if (!$file || !file_exists($file)) {
            throw new ViewNotFoundException("Static partial view '{$view}' not found for app '{$app}'.");
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

    /**
     * Render an external view partial or component generated by a Polyglot service (Python, Node.js, Go, Perl, Java, .NET).
     *
     * REFERENCING EXTERNAL POLYGLOT SERVICES (AVOIDING HTML LITERALS):
     * Instead of writing inline HTML string literals in controllers, you can invoke a polyglot service
     * (e.g. Python analytics script, Node.js React SSR, Go microservice) to process or render the partial markup.
     *
     * @param string $lang The target language (e.g., 'python', 'node', 'go', 'java', 'dotnet', 'perl')
     * @param string $module The relative or absolute path to the polyglot module/script
     * @param string $func The function/routine to execute within the polyglot module
     * @param array $args Data arguments to pass into the polyglot routine
     * @param bool $daemon Whether to use persistent daemon mode for sub-millisecond execution
     * @param int $status HTTP status code
     * @param string|null $cacheKey Optional cache identifier for high-performance caching
     * @param int $ttl Cache time-to-live in seconds
     * @return string
     */
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

    /**
     * Magic zero-configuration rendering for Polyglot services.
     * Automatically discovers the language runtime (.py, .js, .go), resolves entry points, and manages caching under the hood.
     *
     * @param string $name Name of the polyglot partial service (e.g., 'analytics')
     * @param array $data Data arguments to pass to the service
     * @param bool $useCache Whether to use smart caching
     * @param int $ttl Cache TTL in seconds
     * @param int $status HTTP status code
     * @return string
     */
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

    /**
     * Stream a real-time Turbo Stream response generated by a Polyglot service and flush output buffers progressively.
     * Supports both magic auto-discovery syntax and explicit syntax.
     *
     * @param string $langOrName The target language or magic partial name
     * @param mixed $moduleOrArgs The relative path to module OR data arguments array for magic syntax
     * @param string|null $func The function/routine to execute within the polyglot module (if explicit)
     * @param array $args Data arguments to pass into the polyglot routine (if explicit)
     * @param bool $daemon Whether to use persistent daemon mode for sub-millisecond execution
     */
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

    /**
     * Stream a real-time HTML / Turbo Stream response and flush output buffers progressively.
     *
     * STREAMING EXTERNAL FILES (AVOIDING HTML LITERALS):
     * Instead of constructing raw <turbo-stream> HTML string literals in PHP, you can reference external stream
     * template files (e.g. 'streams/live-update.html', 'streams/ticker.blade.php', 'streams/notice.php').
     * These external files contain the stream wrappers and markup, ensuring a clean separation of concerns
     * when updating specific DOM targets on the main page.
     *
     * @param string $view Name or path of the view template (supports explicit .html, .php, .js, .blade.php extensions)
     * @param array $data Data array to pass to the view
     * @param string|null $engine Optional specific rendering engine
     * @throws ViewNotFoundException
     */
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

    /**
     * Render a view and explicitly set the HTTP response status code.
     *
     * REFERENCING EXTERNAL FILES:
     * Easily render standalone external .html, .php, or .js files for composing main pages or handling sub-requests.
     *
     * @param string $view Name or path of the view template
     * @param array $data Data array to pass to the view
     * @param int $status HTTP response status code
     * @param string|null $engine Optional specific rendering engine
     * @return string Rendered HTML output
     * @throws ViewNotFoundException
     */
    protected function renderView(string $view, array $data = [], int $status = 200, ?string $engine = null): string
    {
        http_response_code($status);
        return $this->render($view, $data, $engine);
    }

    /**
     * Send a JSON response payload and terminate execution.
     *
     * @param array|object $data The response payload
     * @param int $status HTTP response status code
     */
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

    /**
     * Issue an HTTP redirect header and terminate execution.
     *
     * @param string $url Target destination URL
     * @param array $flashes Optional flash messages to set before redirecting
     */
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

    /**
     * Generates a safe, inline SPP-UX alert block containing the exception trace.
     * Prevents the entire view rendering engine from crashing on a single partial failure.
     *
     * @param \Throwable $e
     * @param string $file
     * @return string
     */
    public static function renderErrorBoundary(\Throwable $e, string $file): string
    {
        $shortFile = basename($file);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        return <<<HTML
<div class="sppux-alert sppux-alert-danger" style="margin: 1rem 0; padding: 1rem; border-radius: 8px; font-family: system-ui; text-align: left;">
    <strong>💥 View Template Error: <code>{$shortFile}</code></strong><br>
    <span style="font-family: monospace; font-size: 0.85rem; opacity: 0.8;">{$message}</span>
</div>
HTML;
    }

    /**
     * Securely include a native PHP view template within an isolated scope to prevent variable collisions.
     *
     * @param string $__file Absolute view file path
     * @param array $__data View variables
     * @return string
     */
    private static function renderIsolated(string $__file, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            return self::renderErrorBoundary($e, $__file);
        }
        return ob_get_clean();
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
