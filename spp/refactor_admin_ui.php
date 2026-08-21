<?php
// Refactor Script for Admin UI -> CLI Parity

$servicesDir = __DIR__ . '/admin/services';
$commandsDir = __DIR__ . '/commands';

$files = glob($servicesDir . '/*.php');

foreach ($files as $file) {
    $basename = basename($file, '.php');
    if ($basename === 'test_bridge') continue; 

    $content = file_get_contents($file);
    
    $tokens = token_get_all($content);
    $functions = [];
    $inFunction = false;
    $braceCount = 0;
    $currentFunc = '';
    $currentBody = '';
    $funcName = '';
    $funcStart = 0;
    
    for ($i = 0; $i < count($tokens); $i++) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_FUNCTION) {
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $tokens[$j][1];
                if (strpos($name, 'live_') === 0) {
                    $inFunction = true;
                    $funcName = $name;
                    $currentBody = '';
                    $braceCount = 0;
                    $funcStart = $i;
                }
            }
        }
        
        if ($inFunction) {
            $char = is_array($t) ? $t[1] : $t;
            $currentBody .= $char;
            
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
                if ($braceCount === 0) {
                    $inFunction = false;
                    $functions[$funcName] = [
                        'full' => $currentBody,
                        'name' => $funcName
                    ];
                }
            }
        }
    }

    if (empty($functions)) continue;

    $commandName = 'Admin' . $basename . 'Command';
    $cmdIdentifier = 'admin:' . strtolower($basename);
    
    $commandClass = "<?php\nnamespace SPP\\CLI\\Commands;\n\nuse SPP\\CLI\\Command;\n\nclass {$commandName} extends Command\n{\n";
    $commandClass .= "    protected string \$name = '{$cmdIdentifier}';\n";
    $commandClass .= "    protected string \$description = 'Manage Admin {$basename} operations. Usage: {$cmdIdentifier} <action> [--payload=...] [--json]';\n\n";
    $commandClass .= "    public function isHidden(): bool { return true; }\n\n";
    $commandClass .= "    public function execute(array \$args): void\n    {\n";
    $commandClass .= "        \$action = \$this->getArgument(\$args, 0);\n";
    $commandClass .= "        \$payloadRaw = \$this->getOption(\$args, 'payload', '{}');\n";
    $commandClass .= "        \$payload = json_decode(\$payloadRaw, true) ?: [];\n\n";
    $commandClass .= "        \$methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', \$action)));\n";
    $commandClass .= "        if (method_exists(\$this, \$methodName)) {\n";
    $commandClass .= "            \$this->\$methodName(\$payload, \$args);\n";
    $commandClass .= "        } else {\n";
    $commandClass .= "            \$this->json(['success' => false, 'error' => \"Unknown action: \$action\"], \$args);\n";
    $commandClass .= "        }\n    }\n\n";

    $newFileContent = $content;

    foreach ($functions as $funcName => $data) {
        $actionNameRaw = substr($funcName, 5); // remove 'live_'
        $parts = explode('_', strtolower($actionNameRaw));
        if ($parts[0] === strtolower($basename)) {
            array_shift($parts);
        }
        $actionArg = implode('_', $parts);
        if (empty($actionArg)) $actionArg = 'default';

        $methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', $actionArg)));

        $fullFunc = $data['full'];
        $firstBrace = strpos($fullFunc, '{');
        $lastBrace = strrpos($fullFunc, '}');
        $innerBody = substr($fullFunc, $firstBrace + 1, $lastBrace - $firstBrace - 1);

        $innerBody = preg_replace('/\$params/', '$payload', $innerBody);
        
        // Fix any 'return $la->...;' by stripping the return and adding 'return;' at the end of the statement block!
        $innerBody = preg_replace('/return\s+\$la->(.*?);/s', '$la->$1;' . "\n" . '        return;', $innerBody);
        
        // $la->setData(X) -> $this->json(X, $args); return;
        $innerBody = preg_replace('/\$la->setData\((.*?)\);/s', '$this->json($1, $args); return;', $innerBody);
        
        // $la->setStatus('error')->notify(X, Y) -> $this->json(['success'=>false, 'error'=>X], $args); return;
        $innerBody = preg_replace('/\$la->setStatus\([\'"]error[\'"]\)->notify\(([^;]+)\);/s', '$this->json([\'success\' => false, \'error\' => $1], $args); return;', $innerBody);
        
        // $la->notify(X) -> $this->json(['success'=>true, 'message'=>X], $args); return;
        $innerBody = preg_replace('/\$la->notify\(([^;]+)\);/s', '$this->json([\'success\' => true, \'message\' => $1], $args); return;', $innerBody);

        $commandClass .= "    private function {$methodName}(array \$payload, array \$args): void {\n";
        $commandClass .= $innerBody . "\n    }\n\n";

        $proxyBody = "{\n";
        $proxyBody .= "    \$res = \\SPP\\CLI\\CommandManager::execute('{$cmdIdentifier}', ['{$actionArg}', '--payload' => json_encode(\$params), '--json' => '1']);\n";
        $proxyBody .= "    if (\$res['success']) {\n";
        $proxyBody .= "        \$data = json_decode(\$res['output'], true);\n";
        $proxyBody .= "        if (isset(\$data['success']) && !\$data['success']) {\n";
        $proxyBody .= "            \$la->setStatus('error')->notify(\$data['error'] ?? 'Command failed.');\n";
        $proxyBody .= "        } elseif (isset(\$data['modal'])) {\n";
        $proxyBody .= "            \$la->modal(\$data['modal']['title'], \$data['modal']['html'], \$data['modal']['buttons'] ?? []);\n";
        $proxyBody .= "        } elseif (isset(\$data['message'])) {\n";
        $proxyBody .= "            \$la->notify(\$data['message']);\n";
        $proxyBody .= "            if (!empty(\$data['closeModal'])) \$la->closeModal();\n";
        $proxyBody .= "            if (!empty(\$data['refresh'])) \$la->refresh();\n";
        $proxyBody .= "        } else {\n";
        $proxyBody .= "            \$la->setData(\$data ?: []);\n";
        $proxyBody .= "        }\n";
        $proxyBody .= "    } else {\n";
        $proxyBody .= "        \$la->setStatus('error')->notify(\$res['error']);\n";
        $proxyBody .= "    }\n";
        $proxyBody .= "}";

        $newFunc = substr($fullFunc, 0, $firstBrace) . $proxyBody;
        $newFileContent = str_replace($fullFunc, $newFunc, $newFileContent);
    }

    $commandClass .= "}\n";

    file_put_contents($commandsDir . '/' . $commandName . '.php', $commandClass);
    file_put_contents($file, $newFileContent);

    echo "Refactored $basename\n";
}
