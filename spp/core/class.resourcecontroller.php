<?php

namespace SPP\Core;

use SPP\Exceptions\EntityNotFoundException;
use SPPMod\SPPView\ViewController;

if (!class_exists('\\SPPMod\\SPPView\\ViewController', false)) {
    $vcFile = dirname(__DIR__) . '/modules/spp/sppview/class.viewcontroller.php';
    if (file_exists($vcFile)) {
        require_once $vcFile;
    }
}

/**
 * Class ResourceController
 * Provides a base for RESTful resource management in SPP.
 * Inherits all enterprise ViewController capabilities natively without code duplication:
 *   - #[Middleware(ClassName::class)] : Class and method-level middleware attributes.
 *   - $this->validate(array $rules)   : Request validation using ViewValidator.
 *   - $this->hydrate(string $class)   : DTO/Entity hydration via DataTransformers.
 *   - $this->share($key, $value)      : Context sharing across all rendered views.
 *   - $this->getCspNonce()            : Per-request Content Security Policy nonces.
 *   - $this->stream($view, $data)     : Real-time Turbo Streams streaming.
 *   - $this->json($data, $status)     : Standardized JSON responses.
 *   - $this->transitionEntity(...)    : Workflow state transitions with smart content negotiation.
 *   - External Partials / Streams     : Reference standalone .html, .php, or .js files adhering to Golden Rule 1.
 */
abstract class ResourceController extends ViewController
{
    protected string $entityClass;

    public function __construct()
    {
        if (empty($this->entityClass)) {
            // Try to guess from controller name (e.g. UserController -> User)
            $className = (new \ReflectionClass($this))->getShortName();
            $entityName = str_replace('Controller', '', $className);
            $this->entityClass = "\\App\\Default\\Entities\\$entityName";
        }
    }

    public function index($args = null)
    {
        $entityClass = $this->entityClass;
        $entityName = basename(str_replace('\\', '/', $entityClass));
        $slug = strtolower($entityName);

        // Fetch schema & columns
        $schema = method_exists($entityClass, 'getSchema') ? $entityClass::getSchema() : [];
        $columns = !empty($schema) ? array_keys($schema) : ['id'];

        // Live Search support
        $search = $_GET['search'] ?? null;
        if (!empty($search) && !empty($schema)) {
            $conditions = [];
            foreach ($schema as $fName => $fDef) {
                $parsed = is_array($fDef) ? $fDef : (class_exists('\\SPPMod\\SPPDB\\SchemaSyncEngine') ? \SPPMod\SPPDB\SchemaSyncEngine::parseFieldDefinition($fDef) : []);
                if (!empty($parsed['searchable'])) {
                    $conditions[$fName] = $search;
                }
            }
            if (!empty($conditions)) {
                $entities = method_exists($entityClass, 'findWhere') ? $entityClass::findWhere($conditions) : $entityClass::find_all();
            } else {
                $entities = $entityClass::find_all();
            }
        } else {
            $entities = $entityClass::find_all();
        }

        if ($this->isJsonRequest()) {
            return $this->json(['success' => true, 'count' => count($entities), 'data' => $entities]);
        }

        $hasWorkflow = class_exists('\\SPP\\Core\\Interfaces\\WorkflowableInterface') && is_subclass_of($entityClass, '\\SPP\\Core\\Interfaces\\WorkflowableInterface');

        $data = [
            'items' => $entities,
            'entityName' => $entityName,
            'slug' => $slug,
            'resourcePath' => $slug . 's',
            'schema' => $schema,
            'columns' => $columns,
            'hasWorkflow' => $hasWorkflow
        ];

        // 1. Try Custom App Partials
        $customCandidates = [
            "partials/{$slug}_index.blade.php",
            "partials/{$slug}_index.html",
            "partials/{$slug}_index.php"
        ];
        foreach ($customCandidates as $cand) {
            try {
                return $this->renderPartial($cand, $data);
            } catch (\Throwable $e) {}
        }

        // 2. Graceful Master Auto-CRUD Partial Fallback
        try {
            return $this->renderPartial("crud/table.blade.php", $data);
        } catch (\Throwable $e) {}

        return [
            'view' => 'index',
            'data' => $data
        ];
    }

    public function create($args = null)
    {
        $entityClass = $this->entityClass;
        $entityName = basename(str_replace('\\', '/', $entityClass));
        $slug = strtolower($entityName);
        $schema = method_exists($entityClass, 'getSchema') ? $entityClass::getSchema() : [];

        $data = [
            'item' => new $entityClass(),
            'entityName' => $entityName,
            'slug' => $slug,
            'resourcePath' => $slug . 's',
            'schema' => $schema
        ];

        $customCandidates = [
            "partials/{$slug}_create.blade.php",
            "partials/{$slug}_form.blade.php",
            "partials/{$slug}_create.html"
        ];
        foreach ($customCandidates as $cand) {
            try {
                return $this->renderPartial($cand, $data);
            } catch (\Throwable $e) {}
        }

        try {
            return $this->renderPartial("crud/form.blade.php", $data);
        } catch (\Throwable $e) {}

        return [
            'view' => 'create',
            'data' => $data
        ];
    }

    public function store($args = null)
    {
        $data = $this->input();
        $entityClass = $this->entityClass;
        $entity = method_exists($entityClass, 'create') ? $entityClass::create($data) : (new $entityClass());
        if (!method_exists($entityClass, 'create')) {
            $entity->setValues($data);
            $entity->save();
        }
        $id = method_exists($entity, 'getId') ? $entity->getId() : ($entity->id ?? null);

        $slug = strtolower(basename(str_replace('\\', '/', $entityClass)));
        return $this->respond([
            'json' => fn() => ['success' => true, 'id' => $id, 'message' => 'Resource created successfully.'],
            'htmx' => function () use ($slug, $entity) {
                try {
                    return $this->renderPartial("partials/{$slug}_row.html", ['item' => $entity]);
                } catch (\Throwable $e) {
                    $this->redirect(\SPP\App::getBaseUrl() . '/' . $slug . 's');
                }
            },
            'html' => function () use ($slug) {
                $this->redirect(\SPP\App::getBaseUrl() . '/' . $slug . 's');
            }
        ]);
    }

    public function show($id)
    {
        $entityClass = $this->entityClass;
        $entity = new $entityClass($id);
        $entityName = basename(str_replace('\\', '/', $entityClass));
        $slug = strtolower($entityName);
        $schema = method_exists($entityClass, 'getSchema') ? $entityClass::getSchema() : [];

        if ($this->isJsonRequest()) {
            return $this->json(['success' => true, 'data' => $entity]);
        }

        $transitions = [];
        if (method_exists($entity, 'getAvailableTransitions')) {
            $transitions = $entity->getAvailableTransitions();
        }

        $data = [
            'item' => $entity,
            'entityName' => $entityName,
            'slug' => $slug,
            'resourcePath' => $slug . 's',
            'schema' => $schema,
            'transitions' => $transitions
        ];

        $customCandidates = [
            "partials/{$slug}_show.blade.php",
            "partials/{$slug}_show.html",
            "partials/{$slug}_show.php"
        ];
        foreach ($customCandidates as $cand) {
            try {
                return $this->renderPartial($cand, $data);
            } catch (\Throwable $e) {}
        }

        try {
            return $this->renderPartial("crud/show.blade.php", $data);
        } catch (\Throwable $e) {}

        return [
            'view' => 'show',
            'data' => $data
        ];
    }

    public function edit($id, $args = null)
    {
        $entityClass = $this->entityClass;
        $entity = new $entityClass($id);
        $entityName = basename(str_replace('\\', '/', $entityClass));
        $slug = strtolower($entityName);
        $schema = method_exists($entityClass, 'getSchema') ? $entityClass::getSchema() : [];

        $data = [
            'item' => $entity,
            'entityName' => $entityName,
            'slug' => $slug,
            'resourcePath' => $slug . 's',
            'schema' => $schema
        ];

        $customCandidates = [
            "partials/{$slug}_edit.blade.php",
            "partials/{$slug}_form.blade.php",
            "partials/{$slug}_edit.html"
        ];
        foreach ($customCandidates as $cand) {
            try {
                return $this->renderPartial($cand, $data);
            } catch (\Throwable $e) {}
        }

        try {
            return $this->renderPartial("crud/form.blade.php", $data);
        } catch (\Throwable $e) {}

        return [
            'view' => 'edit',
            'data' => $data
        ];
    }

    public function update($id, $args = null)
    {
        $data = $this->input();
        $entityClass = $this->entityClass;
        $entity = new $entityClass($id);
        if (method_exists($entity, 'update')) {
            $entity->update($data);
        } else {
            $entity->setValues($data);
            $entity->save();
        }

        $slug = strtolower(basename(str_replace('\\', '/', $entityClass)));
        return $this->respond([
            'json' => fn() => ['success' => true, 'message' => 'Resource updated successfully.'],
            'htmx' => function () use ($slug, $entity) {
                try {
                    return $this->renderPartial("partials/{$slug}_row.html", ['item' => $entity, 'updated' => true]);
                } catch (\Throwable $e) {
                    $this->redirect(\SPP\App::getBaseUrl() . '/' . $slug . 's');
                }
            },
            'html' => function () use ($slug) {
                $this->redirect(\SPP\App::getBaseUrl() . '/' . $slug . 's');
            }
        ]);
    }

    public function destroy($id)
    {
        $entityClass = $this->entityClass;
        $entity = new $entityClass($id);
        
        if (method_exists($entity, 'delete')) {
            $entity->delete();
        } else {
            $db = \SPP\DB::getInstance();
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', method_exists($entity, 'getTable') ? $entity->getTable() : strtolower((new \ReflectionClass($entity))->getShortName()) . 's');
            $idField = preg_replace('/[^a-zA-Z0-9_]/', '', method_exists($entity, 'getMetadata') ? $entity->getMetadata('id_field') : 'id');
            $sql = "DELETE FROM %tab% WHERE {$idField} = ?";
            $db->exec_squery($sql, $table, [$id]);
        }

        if ($this->isJsonRequest()) {
            return $this->json(['success' => true, 'message' => 'Resource deleted successfully.']);
        }

        $slug = strtolower(basename(str_replace('\\', '/', $entityClass)));
        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            try {
                return $this->renderPartial("partials/{$slug}_deleted.html", ['id' => $id]);
            } catch (\Throwable $e) {
                return ''; // Standard HTMX pattern to remove element from DOM
            }
        }

        $this->redirect(\SPP\App::getBaseUrl() . '/' . $slug . 's');
    }

    public function applyTransitionAction($id, string $transition = '')
    {
        $entityClass = $this->entityClass;
        $entity = new $entityClass($id);
        $context = array_merge($_POST, $_GET);
        $trans = $transition ?: ($context['transition'] ?? '');

        $result = $this->transitionEntity($entity, $trans, $context);

        if ($this->isJsonRequest()) {
            return $this->json(['success' => true, 'transition' => $trans, 'state' => method_exists($entity, 'getWorkflowState') ? $entity->getWorkflowState() : ($entity->status ?? '')]);
        }

        $slug = strtolower(basename(str_replace('\\', '/', $entityClass)));
        $this->redirect(\SPP\App::getBaseUrl() . '/' . $slug . 's/' . $id);
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
     * Serve OpenAPI 3.0 specification or interactive documentation for this resource.
     *
     * @param mixed $args
     * @return mixed
     */
    public function openapi($args = null)
    {
        $entityClass = $this->entityClass;
        $spec = class_exists('\\SPPMod\\SPPView\\OpenApiGenerator')
            ? \SPPMod\SPPView\OpenApiGenerator::forEntity($entityClass)
            : ['openapi' => '3.0.3', 'info' => ['title' => basename(str_replace('\\', '/', $entityClass)) . ' API']];

        if ($this->isJsonRequest() || (isset($_SERVER['REQUEST_URI']) && str_ends_with($_SERVER['REQUEST_URI'], '.json'))) {
            return $this->json($spec);
        }

        $docsTemplate = dirname(__DIR__) . '/modules/spp/sppview/resources/views/api/docs.blade.php';
        if (class_exists('\\SPPMod\\Drishyam\\SPPBlade') && file_exists($docsTemplate)) {
            echo \SPPMod\Drishyam\SPPBlade::render($docsTemplate, ['spec' => $spec]);
            return;
        }

        return $this->json($spec);
    }
}
