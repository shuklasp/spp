<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class BridgeCallCommand
 * Internal RPC bridge to invoke PHP methods from Polyglot clients (like Python).
 */
class BridgeCallCommand extends Command
{
    protected string $name = 'bridge:call';
    protected string $description = 'Internal RPC bridge to invoke PHP methods from Polyglot clients';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $class = $this->getArgument($args, 0) ?? null;
        $method = $this->getArgument($args, 1) ?? null;
        $argsJson = $this->getArgument($args, 2) ?? '[]';

        if (!$class || !$method) {
            echo json_encode(['success' => false, 'error' => 'Class or method not provided']);
            return;
        }

        try {
            $params = json_decode($argsJson, true) ?: [];
            $class = str_replace('.', '\\', $class); // Allow dot notation for namespaces
            
            if (method_exists($class, $method)) {
                $reflection = new \ReflectionMethod($class, $method);
                if ($reflection->isStatic()) {
                    $result = call_user_func_array([$class, $method], $params);
                } else {
                    $instance = \SPP\App::getInstance()->get($class);
                    $result = call_user_func_array([$instance, $method], $params);
                }
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => "Method {$method} not found in {$class}"]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
