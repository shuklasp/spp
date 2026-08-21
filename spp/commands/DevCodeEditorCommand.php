<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DevCodeEditorCommand extends Command
{
    protected string $name = 'dev:codeeditor';
    protected string $description = 'Manage Dev CodeEditor operations. Usage: dev:codeeditor <action> [--payload=...] [--json]';

    public function isHidden(): bool { return true; }

    public function execute(array $args): void
    {
        $action = $this->getArgument($args, 0) ?? 'default';
        $payloadRaw = $this->getOption($args, 'payload', '{}');
        $payload = json_decode($payloadRaw, true) ?: [];

        $methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));
        if (method_exists($this, $methodName)) {
            $this->$methodName($payload, $args);
        } else {
            $this->json(['success' => false, 'error' => "Unknown action: $action"], $args);
        }
    }

    private function handleListfiles(array $payload, array $args): void {

    $path = $payload['path'] ?? getProjectRoot();
    $path = realpath($path);

    if ($path === false || strpos($path, realpath(getProjectRoot())) !== 0) {
        $this->json(['success' => false, 'error' => "Invalid path."], $args); return;
        return;
    }

    $files = [];
    $dirs = [];

    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $path . DIRECTORY_SEPARATOR . $item;
        $relPath = substr($fullPath, strlen(realpath(getProjectRoot())) + 1);
        
        $info = [
            'name' => $item,
            'path' => str_replace('\\', '/', $relPath),
            'isDir' => is_dir($fullPath)
        ];

        if (is_dir($fullPath)) {
            $dirs[] = $info;
        } else {
            $files[] = $info;
        }
    }

    $this->json([
        'currentPath' => str_replace('\\', '/', substr($path, strlen(realpath(getProjectRoot())) + 1)),
        'items' => array_merge($dirs, $files)
    ], $args); return;

    }

    private function handleReadfile(array $payload, array $args): void {

    $path = $payload['path'] ?? '';
    if (!$path) $this->json(['success' => false, 'error' => "File path required."], $args); return;
        return;

    $fullPath = realpath(getProjectRoot() . DIRECTORY_SEPARATOR . $path);
    if ($fullPath === false || strpos($fullPath, realpath(getProjectRoot())) !== 0 || !is_file($fullPath)) {
        $this->json(['success' => false, 'error' => "Invalid file path."], $args); return;
        return;
    }

    $content = file_get_contents($fullPath);
    $this->json([
        'path' => $path,
        'content' => $content
    ], $args); return;

    }

    private function handleWritefile(array $payload, array $args): void {

    $path = $payload['path'] ?? '';
    $content = $payload['content'] ?? '';

    if (!$path) $this->json(['success' => false, 'error' => "File path required."], $args); return;
        return;

    $fullPath = getProjectRoot() . DIRECTORY_SEPARATOR . $path;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Security check to ensure we stay within the app directory
    $realDir = realpath($dir);
    if ($realDir === false || strpos($realDir, realpath(getProjectRoot())) !== 0) {
        $this->json(['success' => false, 'error' => "Invalid file path."], $args); return;
        return;
    }

    if (file_put_contents($fullPath, $content) !== false) {
        $this->json(['success' => true, 'message' => "File saved successfully.", "success"], $args); return;
        return;
    } else {
        $this->json(['success' => false, 'error' => "Failed to save file."], $args); return;
        return;
    }

    }

    private function handleGetcompletions(array $payload, array $args): void {

    // 1. Gather all declared classes
    $classes = get_declared_classes();
    $classCompletions = [];
    foreach ($classes as $class) {
        if (strpos($class, 'SPP\\') === 0 || strpos($class, 'SPPMod\\') === 0) {
            $classCompletions[] = [
                'label' => $class,
                'kind' => 7, // Monaco completion item kind for Class
                'insertText' => $class,
                'detail' => 'SPP Framework Class'
            ];
        } else {
            $classCompletions[] = [
                'label' => $class,
                'kind' => 7,
                'insertText' => $class,
                'detail' => 'Class'
            ];
        }
    }

    // 2. Gather functions
    $functions = get_defined_functions();
    $funcCompletions = [];
    foreach ($functions['internal'] as $func) {
        $funcCompletions[] = [
            'label' => $func,
            'kind' => 3, // Function
            'insertText' => $func . '()',
            'detail' => 'PHP Built-in'
        ];
    }
    foreach ($functions['user'] as $func) {
        $funcCompletions[] = [
            'label' => $func,
            'kind' => 3,
            'insertText' => $func . '()',
            'detail' => 'User Function'
        ];
    }

    // 3. Common SPP methods (for $this-> context in controllers)
    $sppMethods = [
        ['label' => 'render', 'kind' => 2, 'insertText' => 'render(\'${1:view}\', ${2:[]});', 'insertTextRules' => 4, 'detail' => 'Render View'],
        ['label' => 'renderPartial', 'kind' => 2, 'insertText' => 'renderPartial(\'${1:view}\', ${2:[]});', 'insertTextRules' => 4, 'detail' => 'Render Partial (HTMX)'],
        ['label' => 'stream', 'kind' => 2, 'insertText' => 'stream(\'${1:stream}\', ${2:[]});', 'insertTextRules' => 4, 'detail' => 'Render Turbo Stream'],
        ['label' => 'transitionEntity', 'kind' => 2, 'insertText' => 'transitionEntity(${1:$entity}, \'${2:transitionName}\', ${3:[]});', 'insertTextRules' => 4, 'detail' => 'Workflow Transition'],
        ['label' => 'json', 'kind' => 2, 'insertText' => 'json(${1:[]});', 'insertTextRules' => 4, 'detail' => 'JSON Response']
    ];

    $this->json([
        'completions' => array_merge($classCompletions, $funcCompletions, $sppMethods)
    ], $args); return;

    }

}
