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
     * @var array Route parameters matched from URL path placeholders and query string.
     */
    public array $routeParams = [];

    /**
     * @var array|null Cached parsed JSON input payload.
     */
    protected ?array $jsonPayload = null;

    /**
     * Retrieve the parsed JSON payload from the request body.
     */
    protected function getJsonPayload(): array
    {
        if ($this->jsonPayload === null) {
            $input = file_get_contents('php://input');
            $decoded = !empty($input) ? json_decode($input, true) : null;
            $this->jsonPayload = is_array($decoded) ? $decoded : [];
        }
        return $this->jsonPayload;
    }

    /**
     * Retrieve unified request input (route params, JSON payload, POST, GET).
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function input(?string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($_GET, $this->getJsonPayload(), $_POST, $this->routeParams);
        if ($key === null) {
            return $all;
        }
        return $all[$key] ?? $default;
    }

    /**
     * Retrieve query parameter from $_GET.
     */
    protected function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Retrieve form parameter from $_POST or JSON payload.
     */
    protected function post(...$args)
    {
        $key = $args[0] ?? null;
        $default = $args[1] ?? null;
        $merged = array_merge($this->getJsonPayload(), $_POST);
        if ($key === null) {
            return $merged;
        }
        return $merged[$key] ?? $default;
    }

    /**
     * Retrieve uploaded file info from $_FILES.
     */
    protected function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * Check if an input key exists in the request.
     */
    protected function has(string $key): bool
    {
        $all = $this->input();
        return array_key_exists($key, $all);
    }

    /**
     * Retrieve only the specified keys from the request input.
     */
    protected function only(array $keys): array
    {
        $all = $this->input();
        $result = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $all)) {
                $result[$k] = $all[$k];
            }
        }
        return $result;
    }

    /**
     * Retrieve all input except the specified keys.
     */
    protected function except(array $keys): array
    {
        $all = $this->input();
        foreach ($keys as $k) {
            unset($all[$k]);
        }
        return $all;
    }

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
     * Resolve the application context for this controller based on namespace or active context.
     *
     * @return string Application name
     */
    public function getAppName(): string
    {
        $class = static::class;
        if (preg_match('/^App\\\\([^\\\\]+)/i', $class, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^([^\\\\]+)\\\\Controllers/i', $class, $matches)) {
            return $matches[1];
        }
        return (class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : null) ?: 'default';
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
    public function validate(array $rules, ?array $data = null): ValidationResult
    {
        if ($data === null) {
            $request = new SPPRequest();
            $data = $request->getData();
        }

        // Instantiate an anonymous ViewValidator to run validateAll
        $validator = new class extends ViewValidator {};
        $validator->setSilent(true);
        $result = $validator->validateAll($data, $rules);

        if (!$result->isValid()) {
            if (session_status() === PHP_SESSION_ACTIVE || !headers_sent()) {
                if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                    @session_start();
                }
                $_SESSION['_spp_old_input'] = $data;
            }
            if (class_exists('\\SPPMod\\SPPView\\SPP_ViewErrors')) {
                foreach ($result->getErrors() as $field => $messages) {
                    foreach ($messages as $msg) {
                        SPP_ViewErrors::addError($field, $msg, 'error');
                        SPP_ViewErrors::addError('global', "{$field}: {$msg}", 'error');
                    }
                }
            }
        } else {
            unset($_SESSION['_spp_old_input']);
        }

        $validated = [];
        foreach ($rules as $field => $r) {
            if (array_key_exists($field, $data)) {
                $validated[$field] = $data[$field];
            }
        }
        $result->setValidatedData($validated);

        return $result;
    }

    /**
     * Validate incoming request data and return ONLY the validated fields.
     * If validation fails, automatically responds with 422 JSON for API requests
     * or flashes errors to session and redirects back for web requests.
     *
     * @param array $rules Validation rules
     * @param array|null $data Input data to validate (defaults to $this->input())
     * @return array Validated data array containing only keys defined in rules
     */
    protected function validateOrFail(array $rules, ?array $data = null): array
    {
        $input = $data ?? $this->input();
        $result = $this->validate($rules, $input);

        if (!$result->isValid()) {
            if ($this->isJsonRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'errors' => $result->getErrors()
                ], 422);
            }

            if ($this->isHtmx()) {
                http_response_code(422);
            }

            $referer = $_SERVER['HTTP_REFERER'] ?? '/';
            $this->redirect($referer);
        }

        $validated = [];
        foreach ($rules as $field => $r) {
            if (array_key_exists($field, $input)) {
                $validated[$field] = $input[$field];
            }
        }
        return $validated;
    }

    /**
     * Validate incoming request against a declared Form YAML schema.
     * Automatically extracts rules for all declared fields in the Form YAML.
     * If $formId is omitted, auto-detects from request '_spp_form' or 'form_id'.
     *
     * @param string|null $formId The form ID or name (e.g. 'student_admission'), or null to auto-detect
     * @param array|null $data Input data to validate (defaults to request input)
     * @return ValidationResult
     */
    public function validateForm(?string $formId = null, ?array $data = null): ValidationResult
    {
        $app = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : null;

        if ($formId === null) {
            $input = $data ?? $this->input();
            $formId = $input['_spp_form'] ?? ($input['form_id'] ?? null);
            if (!$formId && isset($_POST['_spp_form'])) {
                $formId = $_POST['_spp_form'];
            }
            if (!$formId && isset($_POST['form_id'])) {
                $formId = $_POST['form_id'];
            }
            if (!$formId) {
                throw new \InvalidArgumentException("No Form YAML ID specified and no '_spp_form' field found in request.");
            }
        }

        $config = Forms::getFormConfig($formId, $app);

        if (!$config) {
            throw new \InvalidArgumentException("Form YAML schema '{$formId}' not found.");
        }

        $fields = $config['fields'] ?? ($config['elements'] ?? []);
        $rules = [];

        foreach ($fields as $key => $fieldDef) {
            $fieldName = is_string($key) ? $key : ($fieldDef['name'] ?? $fieldDef['id'] ?? null);
            if (!$fieldName) continue;

            $validation = null;
            if (is_array($fieldDef)) {
                if (isset($fieldDef['validations']) && is_array($fieldDef['validations'])) {
                    $ruleTypes = [];
                    foreach ($fieldDef['validations'] as $vItem) {
                        if (is_string($vItem)) {
                            $ruleTypes[] = $vItem;
                        } elseif (is_array($vItem) && isset($vItem['type'])) {
                            $ruleTypes[] = $vItem['type'];
                        }
                    }
                    $validation = implode('|', $ruleTypes);
                } else {
                    $validation = $fieldDef['validation'] ?? ($fieldDef['rules'] ?? ($fieldDef['rule'] ?? null));
                }
            } elseif (is_string($fieldDef)) {
                $validation = $fieldDef;
            }

            if ($validation !== null && $validation !== '') {
                $rules[$fieldName] = $validation;
            }
        }

        if (isset($config['validation']) && is_array($config['validation'])) {
            $rules = array_merge($rules, $config['validation']);
        }

        return $this->validate($rules, $data);
    }

    /**
     * Validate request against a Form YAML schema, throwing an exception or redirecting on failure.
     * Returns ONLY the validated field values.
     *
     * @param string|null $formId Optional form ID or null to auto-detect
     * @param array|null $data
     * @return array<string, mixed>
     */
    public function validateFormOrFail(?string $formId = null, ?array $data = null): array
    {
        $app = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : null;

        if ($formId === null) {
            $input = $data ?? $this->input();
            $formId = $input['_spp_form'] ?? ($input['form_id'] ?? null);
            if (!$formId && isset($_POST['_spp_form'])) {
                $formId = $_POST['_spp_form'];
            }
            if (!$formId && isset($_POST['form_id'])) {
                $formId = $_POST['form_id'];
            }
            if (!$formId) {
                throw new \InvalidArgumentException("No Form YAML ID specified and no '_spp_form' field found in request.");
            }
        }

        $config = Forms::getFormConfig($formId, $app);

        if (!$config) {
            throw new \InvalidArgumentException("Form YAML schema '{$formId}' not found.");
        }

        $fields = $config['fields'] ?? ($config['elements'] ?? []);
        $rules = [];

        foreach ($fields as $key => $fieldDef) {
            $fieldName = is_string($key) ? $key : ($fieldDef['name'] ?? $fieldDef['id'] ?? null);
            if (!$fieldName) continue;

            $validation = null;
            if (is_array($fieldDef)) {
                if (isset($fieldDef['validations']) && is_array($fieldDef['validations'])) {
                    $ruleTypes = [];
                    foreach ($fieldDef['validations'] as $vItem) {
                        if (is_string($vItem)) {
                            $ruleTypes[] = $vItem;
                        } elseif (is_array($vItem) && isset($vItem['type'])) {
                            $ruleTypes[] = $vItem['type'];
                        }
                    }
                    $validation = implode('|', $ruleTypes);
                } else {
                    $validation = $fieldDef['validation'] ?? ($fieldDef['rules'] ?? ($fieldDef['rule'] ?? null));
                }
            } elseif (is_string($fieldDef)) {
                $validation = $fieldDef;
            }

            if ($validation !== null && $validation !== '') {
                $rules[$fieldName] = $validation;
            }
        }

        if (isset($config['validation']) && is_array($config['validation'])) {
            $rules = array_merge($rules, $config['validation']);
        }

        return $this->validateOrFail($rules, $data);
    }

    /**
     * Ergonomic one-line handler for failed validation:
     * - Returns 422 JSON response if AJAX / JSON request.
     * - Flashes errors and old input, and redirects back for standard browser requests.
     *
     * @param ValidationResult $validation
     * @param string|null $fallbackUrl
     * @param string|null $message
     * @return mixed
     */
    public function backWithErrors(ValidationResult $validation, ?string $fallbackUrl = null, ?string $message = null): mixed
    {
        if ($this->isJsonRequest() || $this->isAjax() || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            $payload = [
                'success' => false,
                'status' => 'error',
                'message' => $message ?: 'The given data was invalid.',
                'errors' => $validation->errors()
            ];
            $this->json($payload, 422);
            return $payload;
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $input = $this->input();
        $_SESSION['_spp_old'] = $input;
        $_SESSION['_spp_old_input'] = $input;
        $_SESSION['_spp_errors'] = $validation->errors();

        if (class_exists('\\SPPMod\\SPPView\\SPP_ViewErrors')) {
            foreach ($validation->getErrors() as $field => $messages) {
                foreach ($messages as $msg) {
                    SPP_ViewErrors::addError($field, $msg, 'error');
                }
            }
        }

        if (method_exists($this, 'flash')) {
            $this->flash('error', $message ?: 'Please correct the errors below.');
        }

        if ($this->isHtmx()) {
            http_response_code(422);
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? ($fallbackUrl ?: '/');
        return $this->redirect($referer);
    }

    /**
     * Retrieve flashed old input from previous submission.
     *
     * @param string|null $field
     * @param mixed $default
     * @return mixed
     */
    public static function getOldInput(?string $field = null, mixed $default = null): mixed
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $old = $_SESSION['_spp_old'] ?? ($_SESSION['_spp_old_input'] ?? []);
        if ($field === null) {
            return $old;
        }

        return $old[$field] ?? $default;
    }

    /**
     * Retrieve flashed validation errors from previous submission.
     *
     * @param string|null $field
     * @return mixed
     */
    public static function getFlashErrors(?string $field = null): mixed
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $errors = $_SESSION['_spp_errors'] ?? [];
        if ($field === null) {
            return $errors;
        }

        return $errors[$field] ?? null;
    }

    /**
     * Instance helper to retrieve flashed old input.
     *
     * @param string|null $field
     * @param mixed $default
     * @return mixed
     */
    protected function old(?string $field = null, mixed $default = null): mixed
    {
        return self::getOldInput($field, $default);
    }

    /**
     * Fluent multi-format content negotiation (Turbo Streams, HTMX/partials, JSON, HTML).
     *
     * @param array<string, callable|mixed> $formatMap
     * @return mixed
     */
    protected function respond(array $formatMap): mixed
    {
        // 1. Turbo Stream
        $isTurboStream = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'text/vnd.turbo-stream.html'));
        if ($isTurboStream && (isset($formatMap['turbo_stream']) || isset($formatMap['stream']))) {
            $handler = $formatMap['turbo_stream'] ?? $formatMap['stream'];
            return is_callable($handler) ? $handler() : $handler;
        }

        // 2. HTMX or Turbo Frame
        $isHtmx = $this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME']);
        if ($isHtmx && (isset($formatMap['htmx']) || isset($formatMap['partial']))) {
            $handler = $formatMap['htmx'] ?? $formatMap['partial'];
            return is_callable($handler) ? $handler() : $handler;
        }

        // 3. JSON Request
        if ($this->isJsonRequest() && isset($formatMap['json'])) {
            $val = is_callable($formatMap['json']) ? $formatMap['json']() : $formatMap['json'];
            if ($val instanceof \SPP\Response) {
                return $val;
            }
            return $this->json($val);
        }

        // 4. Standard HTML fallback
        if (isset($formatMap['html'])) {
            $handler = $formatMap['html'];
            return is_callable($handler) ? $handler() : $handler;
        }

        // 5. Fallback to first available handler
        $first = reset($formatMap);
        return is_callable($first) ? $first() : $first;
    }

    /**
     * Friendly developer fluent view response generator.
     * Returns a ViewResponse implementing \Stringable, supporting ->with() chaining
     * and structured inspection during Parikshak testing.
     *
     * @param string $view
     * @param array $data
     * @param string|null $engine
     * @return ViewResponse
     */
    protected function view(...$args)
    {
        $view = $args[0] ?? '';
        $data = $args[1] ?? [];
        $engine = $args[2] ?? null;

        if (!class_exists('\\SPPMod\\SPPView\\ViewResponse')) {
            require_once __DIR__ . '/class.viewresponse.php';
        }
        return new ViewResponse($this, (string)$view, is_array($data) ? $data : [], $engine);
    }

    /**
     * Friendly developer alias for renderPartial().
     *
     * @param string $view
     * @param array $data
     * @param string|null $engine
     * @return string
     */
    protected function partial(string $view, array $data = [], ?string $engine = null): string
    {
        return $this->renderPartial($view, $data, $engine);
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
        // Enforce Directory Micro-Firewall & ABAC if view or controller is protected
        if (!class_exists('\SPPMod\SPPStorage\ManifestSecurityGuard')) {
            $guardFile = dirname(__DIR__, 2) . '/sppstorage/src/ManifestSecurityGuard.php';
            if (file_exists($guardFile)) {
                require_once $guardFile;
            }
        }
        if (class_exists('\SPPMod\SPPStorage\ManifestSecurityGuard')) {
            $ref = new \ReflectionClass($this);
            $ctrlFile = $ref->getFileName();
            if ($ctrlFile && file_exists($ctrlFile)) {
                \SPPMod\SPPStorage\ManifestSecurityGuard::enforce($ctrlFile);
            }
        }
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

        $app = $this->getAppName();
        
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
        if (!isset($data['errors'])) {
            $data['errors'] = new class {
                protected array $errors = [];
                public function __construct() {
                    $this->errors = class_exists('\\SPPMod\\SPPView\\SPP_ViewErrors') ? \SPPMod\SPPView\SPP_ViewErrors::getErrors() : [];
                }
                public function has(string $key): bool {
                    return !empty($this->errors[$key]);
                }
                public function first(string $key): ?string {
                    return !empty($this->errors[$key]) ? (is_array($this->errors[$key]) ? reset($this->errors[$key]) : $this->errors[$key]) : null;
                }
                public function get(string $key): array {
                    return (array)($this->errors[$key] ?? []);
                }
                public function all(): array {
                    return $this->errors;
                }
                public function any(): bool {
                    return !empty($this->errors);
                }
            };
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
            // 2. Fallback: Native PHP/Blade view rendering workflow via ViewLocator
            $file = ViewLocator::locate($view, $app);

            if ($file) {
                if (str_ends_with($file, '.blade.php') && class_exists('\\SPPMod\\Drishyam\\SPPBlade')) {
                    $output = \SPPMod\Drishyam\SPPBlade::render($file, $data, $app);
                } else {
                    $output = self::renderIsolated($file, $data);
                }
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

        // Cakewalk DevLens HUD Injection (Local Development Only)
        if ($this->isDevLensEnabled() && stripos($output, '</body>') !== false && stripos($output, 'id="spp-devlens"') === false && !$this->isAjax() && !$this->isHtmx()) {
            $lensHtml = $this->renderDevLens($view, $data);
            if ($lensHtml) {
                $pos = strripos($output, '</body>');
                if ($pos !== false) {
                    $output = substr_replace($output, $lensHtml . "\n</body>", $pos, 7);
                }
            }
        }

        return $output;
    }

    /**
     * Determine if Cakewalk DevLens HUD is active.
     */
    protected function isDevLensEnabled(): bool
    {
        // 1. Block on non-local / public production domains
        $host = strtolower(trim(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]));
        if ($host !== '' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true) && !str_ends_with($host, '.local') && !str_ends_with($host, '.test')) {
            return false;
        }

        // 2. Block if explicitly set to production environments
        $env = strtolower((string)(getenv('APP_ENV') ?: (getenv('SPP_ENV') ?: ($_ENV['APP_ENV'] ?? (defined('APP_ENV') ? APP_ENV : '')))));
        if (in_array($env, ['prod', 'production', 'live'], true)) {
            return false;
        }

        $isLocal = in_array($env, ['local', 'dev', 'development', 'testing', 'test']);
        $isDebug = defined('SPP_DEBUG') && SPP_DEBUG;
        return $isLocal || $isDebug;
    }

    /**
     * Render the Cakewalk DevLens Blade HUD template.
     */
    protected function renderDevLens(string $currentView, array $currentData): string
    {
        try {
            $lensFile = __DIR__ . '/resources/views/dev/lens.blade.php';
            if (!file_exists($lensFile)) {
                return '';
            }
            $lensData = array_merge($currentData, [
                'view' => $currentView,
                'app_name' => \SPP\Scheduler::getContext() ?: 'default',
            ]);

            if (class_exists('\\SPPMod\\Drishyam\\SPPBlade')) {
                return \SPPMod\Drishyam\SPPBlade::render($lensFile, $lensData);
            }

            // Fallback rendering
            ob_start();
            extract($lensData, EXTR_SKIP);
            include $lensFile;
            return ob_get_clean() ?: '';
        } catch (\Throwable $e) {
            return '';
        }
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

        // Validate partial contract if props are registered in FilePropsManager
        if (class_exists('\\SPPMod\\SPPStorage\\FilePropsManager')) {
            $app = \SPP\Scheduler::getContext();
            $file = ViewLocator::locate($view, $app);
            if ($file && file_exists($file)) {
                $props = \SPPMod\SPPStorage\FilePropsManager::getFileProps($file);
                if (!empty($props['attributes']['required_props']) && is_array($props['attributes']['required_props'])) {
                    $missing = [];
                    foreach ($props['attributes']['required_props'] as $reqProp) {
                        if (!array_key_exists($reqProp, $data)) {
                            $missing[] = $reqProp;
                        }
                    }
                    if (!empty($missing)) {
                        $msg = "Missing required props for partial '{$view}': " . implode(', ', $missing);
                        $isDebug = class_exists('\\SPP\\SPPConfig') ? \SPP\SPPConfig::get('app.debug', false) : false;
                        if ($isDebug || defined('SPP_TEST_MODE')) {
                            throw new \InvalidArgumentException($msg);
                        } else {
                            error_log("[SPP Partial Contract Warning] " . $msg);
                        }
                    }
                }

                if (!class_exists('\\SPPMod\\SPPStorage\\ManifestContractEngine')) {
                    $contractEngineFile = dirname(__DIR__, 2) . '/sppstorage/src/ManifestContractEngine.php';
                    if (file_exists($contractEngineFile)) {
                        require_once $contractEngineFile;
                    }
                }
                if (class_exists('\\SPPMod\\SPPStorage\\ManifestContractEngine')) {
                    $ssr = \SPPMod\SPPStorage\ManifestContractEngine::resolveSsrPolicy($file);
                    if ($ssr['is_streaming'] && !headers_sent()) {
                        header('X-Accel-Buffering: no');
                    }
                    if ($ssr['cache_ttl'] > 0 && !headers_sent()) {
                        $swr = $ssr['stale_while_revalidate'] > 0 ? ", stale-while-revalidate={$ssr['stale_while_revalidate']}" : '';
                        header("Cache-Control: public, max-age={$ssr['cache_ttl']}{$swr}");
                    }
                    if (!empty($ssr['hydration']) && $ssr['hydration'] !== 'load') {
                        $data['spp_hydration'] = $ssr['hydration'];
                    }
                }
            }
        }

        // Resolve and enqueue federated assets from directory manifest
        $federatedAssets = [];
        if (class_exists('\\SPPMod\\SPPView\\AssetFederation')) {
            $federatedAssets = AssetFederation::enqueuePartialAssets($view, \SPP\Scheduler::getContext());
            if (!empty($federatedAssets['view_transition']) && !isset($data['view_transition'])) {
                $data['view_transition'] = $federatedAssets['view_transition'];
            }
        }

        $data['is_partial'] = true;
        try {
            $rendered = $this->render($view, $data, $engine, $cacheKey, $ttl);
        } catch (\Throwable $e) {
            if (!class_exists('\SPPMod\SPPStorage\ManifestContractEngine')) {
                $contractEngineFile = dirname(__DIR__, 2) . '/sppstorage/src/ManifestContractEngine.php';
                if (file_exists($contractEngineFile)) {
                    require_once $contractEngineFile;
                }
            }
            if (class_exists('\SPPMod\SPPStorage\ManifestContractEngine')) {
                $ref = new \ReflectionClass($this);
                $ctrlFile = $ref->getFileName();
                $fallbackMeta = $ctrlFile ? \SPPMod\SPPStorage\ManifestContractEngine::resolveFallback($ctrlFile, $e) : null;
                if ($fallbackMeta && !empty($fallbackMeta['partial']) && $fallbackMeta['partial'] !== $view) {
                    if (!headers_sent() && $fallbackMeta['status_code'] !== 200) {
                        http_response_code($fallbackMeta['status_code']);
                    }
                    $data['error'] = $e->getMessage();
                    $data['fallback'] = $fallbackMeta;
                    return $this->render($fallbackMeta['partial'], $data, $engine);
                }
            }
            throw $e;
        }

        // Prepend federated asset tags for HTMX/Ajax requests if newly discovered
        if (($this->isHtmx() || $this->isAjax()) && !empty($federatedAssets) && class_exists('\\SPPMod\\SPPView\\AssetFederation')) {
            $assetTags = AssetFederation::renderAssetTags($federatedAssets, $this->cspNonce);
            if (!empty($assetTags)) {
                $rendered = $assetTags . $rendered;
            }
        }

        // Automatically inject view-transition-name attribute to outer element if entity id is present
        if (isset($data['id']) && !str_contains($rendered, 'view-transition-name')) {
            $transName = 'vt-' . preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace('/', '-', $view)) . '-' . $data['id'];
            $rendered = preg_replace('/^<([a-z0-9]+)([^>]*)>/is', '<$1$2 style="view-transition-name: ' . $transName . '">', $rendered, 1);
        }

        // Inject progressive island hydration attribute if declared in manifest
        if (!empty($data['spp_hydration']) && !str_contains($rendered, 'data-spp-hydrate')) {
            $rendered = preg_replace('/^<([a-z0-9]+)([^>]*)>/is', '<$1$2 data-spp-hydrate="' . htmlspecialchars((string)$data['spp_hydration']) . '">', $rendered, 1);
        }
        return $rendered;
    }

    /**
     * Render an SPPUX generic widget directly from any controller.
     * Enqueues required runtime assets and outputs the declarative custom element.
     *
     * @param string $name Widget name ('table', 'filter-builder', 'timeline', etc.)
     * @param array $props Widget properties
     * @return string Rendered widget HTML markup
     */
    protected function renderWidget(string $name, array $props = []): string
    {
        if (class_exists('\\SPPMod\\Drishyam\\SPPUX')) {
            return \SPPMod\Drishyam\SPPUX::widget($name, $props);
        }
        return "<!-- SPPUX not loaded -->";
    }

    protected function widget(string $name, array $props = []): string
    {
        return $this->renderWidget($name, $props);
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
            $error = 'PolyglotBridge class not found.';
            $lang = '';
            ob_start();
            include __DIR__ . '/errors/polyglot-error.php';
            return ob_get_clean();
        }

        $res = \SPP\PolyglotBridge::call($lang, $module, $func, $args, $daemon);
        if (!$res['success']) {
            $error = $res['error'] ?? 'Unknown Polyglot Execution Error';
            ob_start();
            include __DIR__ . '/errors/polyglot-error.php';
            return ob_get_clean();
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
            $error = 'TemplateMacros class not found.';
            $lang = '';
            ob_start();
            include __DIR__ . '/errors/polyglot-error.php';
            return ob_get_clean();
        }

        $output = \SPPMod\Drishyam\TemplateMacros::polyglot($name, $data, $useCache, $ttl);

        if ($this->cspNonce !== null && (strpos($output, '<script') !== false || strpos($output, '<style') !== false)) {
            $output = preg_replace('/<script(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<script$1 nonce="' . $this->cspNonce . '">', $output);
            $output = preg_replace('/<style(\s+[^>]*?)?(?<!nonce=["\'][^"\']*["\'])>/i', '<style$1 nonce="' . $this->cspNonce . '">', $output);
        }

        return $output;
    }

    /**
     * Executes any registered polyglot service or script directly and returns decoded data.
     *
     * @param string $path Path to polyglot source/service file
     * @param array $args Parameters passed to the routine
     * @param bool $daemon Whether to execute via persistent daemon
     * @return mixed
     */
    protected function callPolyglot(string $path, array $args = [], bool $daemon = false): mixed
    {
        return \SPP\Polyglot::run($path, $args, $daemon);
    }

    /**
     * Evaluates an ad-hoc expression or snippet in any registered language (Formula DSL, Python, Node, etc.).
     *
     * @param string $lang Language identifier
     * @param string $code Code expression or snippet
     * @param array $context Variable context
     * @return mixed
     */
    protected function evalPolyglot(string $lang, string $code, array $context = []): mixed
    {
        return \SPP\Polyglot::eval($lang, $code, $context);
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
     * Issue an HTTP redirect header and terminate execution (or return Response object).
     *
     * @param string $url Target destination URL
     * @param array $flashes Optional flash messages to set before redirecting
     * @param bool $terminate Whether to terminate execution (default true for backward compatibility)
     * @return \SPP\Response
     */
    protected function redirect(string $url, array $flashes = [], bool $terminate = true): \SPP\Response
    {
        $resp = \SPP\Response::makeRedirect($url);
        foreach ($flashes as $key => $message) {
            $resp->with($key, $message);
        }

        if ($terminate) {
            $resp->send();
            if (php_sapi_name() !== 'cli') {
                exit;
            }
        }

        return $resp;
    }

    /**
     * Fluent redirect helper that returns an SPP Response object without immediate script termination.
     * Ideal for chaining ->with('key', 'val') or ->withInput().
     *
     * @param string $url Target destination URL
     * @return \SPP\Response
     */
    protected function redirectTo(string $url): \SPP\Response
    {
        return $this->redirect($url, [], false);
    }

    /**
     * Lifecycle hook executed before a controller action is called.
     * If this method returns a non-null value (such as a Response or redirect),
     * that value is sent immediately and the action execution is aborted.
     *
     * @param string $method Action method name
     * @param array $params Action parameters
     * @return mixed
     */
    public function beforeAction(string $method, array &$params): mixed
    {
        return null;
    }

    /**
     * Lifecycle hook executed after a controller action is called.
     *
     * @param string $method Action method name
     * @param mixed $result Action result
     * @return void
     */
    public function afterAction(string $method, mixed &$result): void
    {
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
        $message = $e->getMessage();
        ob_start();
        include __DIR__ . '/errors/error-boundary.php';
        return ob_get_clean();
    }

    /**
     * Securely include a native PHP view template within an isolated scope to prevent variable collisions.
     *
     * @param string $__file Absolute view file path
     * @param array $__data View variables
     * @return string
     */
    protected static function renderIsolated(string $__file, array $__data): string
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

    protected function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json') || str_contains($uri, '/api/');
    }

    /**
     * Resolves an SPPForm definition, binds data, evaluates validation and workflow guards,
     * and handles smart content negotiation (HTMX partials, JSON, or form instance).
     *
     * @param string $formName Form identifier or entity slug
     * @param object|array|null $entity Optional entity or data array to bind
     * @param array $options Configuration and rendering options
     * @return \SPPMod\SPPView\ViewForm|string|array
     */
    protected function form(string $formName, $entity = null, array $options = [])
    {
        $app = $this->getAppName();
        $viewForm = null;

        // 1. If entity is provided and has getForm, use it
        if (is_object($entity) && method_exists($entity, 'getForm')) {
            $viewForm = $entity->getForm($formName);
        }

        // 2. Otherwise load via Forms registry
        if (!$viewForm && class_exists('\\SPPMod\\SPPView\\Forms')) {
            $viewForm = \SPPMod\SPPView\Forms::get($formName, $app);
        }

        if (!$viewForm) {
            throw new \SPP\SPPException("Form definition '{$formName}' not found for application '{$app}'.");
        }

        if ($entity !== null) {
            $viewForm->bind($entity);
        }

        if (!empty($options['theme'])) {
            $viewForm->setTheme($options['theme']);
        }

        // Handle Submission on POST / PUT
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && !empty($options['handle_submit'])) {
            $isValid = $viewForm->doValidation();
            if ($isValid && !empty($options['save']) && is_object($entity) && method_exists($entity, 'save')) {
                $entity->save();
            }

            if ($this->isHtmx()) {
                $partial = $options['partial'] ?? ($isValid ? ($options['success_view'] ?? null) : ($options['error_view'] ?? null));
                if ($partial) {
                    return $this->renderPartial($partial, ['form' => $viewForm, 'entity' => $entity, 'valid' => $isValid]);
                }
            }

            if ($this->isJsonRequest()) {
                return $this->json(['success' => $isValid, 'errors' => $viewForm->getErrors()]);
            }
        }

        // If template string is requested or default render
        if (!empty($options['render'])) {
            return $viewForm->getHTML();
        }

        return $viewForm;
    }

    /**
     * Executes a declarative SPPReport, applying optional runtime filters,
     * and renders an external partial, exports (CSV/XLS/PDF), or returns raw data.
     *
     * @param string $reportName Name of the report definition
     * @param array $runtimeFilters Additional filter conditions or overrides
     * @param string $format Output format: 'html', 'json', 'csv', 'xls', 'pdf', 'raw'
     * @return mixed
     */
    protected function report(string $reportName, array $runtimeFilters = [], string $format = 'html')
    {
        $app = $this->getAppName();
        if (!class_exists('\\SPPReport')) {
            if (class_exists('\\SPPMod\\SPPReport\\SPPReport')) {
                class_alias('\\SPPMod\\SPPReport\\SPPReport', '\\SPPReport');
            } else {
                $reportFile = dirname(__DIR__, 2) . '/optional/sppreport/class.sppreport.php';
                if (file_exists($reportFile)) {
                    require_once $reportFile;
                }
            }
        }

        $res = class_exists('\\SPPReport') 
            ? \SPPReport::run($reportName, $runtimeFilters, $app)
            : \SPPMod\SPPReport\SPPReport::run($reportName, $runtimeFilters, $app);

        $data = $res['data'] ?? [];
        $sql = $res['sql'] ?? '';
        $config = $res['config'] ?? [];

        switch (strtolower($format)) {
            case 'json':
                return $this->json(['success' => true, 'count' => count($data), 'data' => $data, 'sql' => $sql]);

            case 'raw':
                return $res;

            case 'csv':
                if (!headers_sent()) {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $reportName . '_' . date('Ymd_His') . '.csv"');
                }
                $out = fopen('php://output', 'w');
                if (!empty($data)) {
                    fputcsv($out, array_keys($data[0]));
                    foreach ($data as $row) {
                        fputcsv($out, array_values($row));
                    }
                }
                fclose($out);
                exit;

            case 'html':
            default:
                $viewData = [
                    'report_name' => $reportName,
                    'data' => $data,
                    'sql' => $sql,
                    'config' => $config,
                    'count' => count($data)
                ];

                // 1. Try App-specific custom partial
                $customCandidates = [
                    "partials/reports/{$reportName}.blade.php",
                    "partials/reports/{$reportName}.php",
                    "partials/{$reportName}_report.blade.php"
                ];
                foreach ($customCandidates as $cand) {
                    try {
                        return $this->renderPartial($cand, $viewData);
                    } catch (\Throwable $e) {}
                }

                // 2. Fall back to master report preview partial
                $masterPartial = dirname(__DIR__, 2) . '/optional/sppreport/views/partials/report_preview.php';
                if (file_exists($masterPartial)) {
                    return self::renderIsolated($masterPartial, ['data' => $viewData]);
                }

                return $this->renderPartial("crud/table.blade.php", ['items' => $data, 'entityName' => $reportName]);
        }
    }

    /**
     * Executes an SPPReport definition and generates an executive glassmorphic KPI card.
     *
     * @param string $reportName Name of the report definition
     * @param array $options KPI options (metric, label, format, icon, trend, period, color)
     * @return string Rendered KPI card markup
     */
    protected function reportKpi(string $reportName, array $options = []): string
    {
        if (!class_exists('\\SPPMod\\Drishyam\\TemplateMacros')) {
            require_once dirname(__DIR__) . '/drishyam/class.templatemacros.php';
        }
        return \SPPMod\Drishyam\TemplateMacros::sppreport_kpi($reportName, $options);
    }
}
