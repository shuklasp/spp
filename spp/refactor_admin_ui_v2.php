<?php
// V2 Refactoring script using Reflection for 100% accurate function body extraction

$servicesDir = __DIR__ . '/admin/services';
$commandsDir = __DIR__ . '/commands';
require_once __DIR__ . '/sppinit.php';

$files = glob($servicesDir . '/*.php');

foreach ($files as $file) {
    $basename = basename($file, '.php');
    if ($basename === 'test_bridge' || $basename === 'General') continue; 
    
    // We include the file to get its functions defined
    require_once $file;
}

$allFuncs = get_defined_functions()['user'];
$liveFuncs = array_filter($allFuncs, fn($f) => strpos($f, 'live_') === 0);
echo "Found " . count($liveFuncs) . " live_ functions.\n";
$groupedFuncs = [];

foreach ($files as $file) {
    $basename = basename($file, '.php');
    if ($basename === 'test_bridge' || $basename === 'General') continue; 
    
    $fileLines = file($file);
    $functionsToReplace = [];
    
    foreach ($allFuncs as $func) {
        if (strpos($func, 'live_') === 0) {
            $ref = new ReflectionFunction($func);
            if (realpath($ref->getFileName()) === realpath($file)) {
                $start = $ref->getStartLine() - 1;
                $end = $ref->getEndLine() - 1;
                
                $fullFuncLines = array_slice($fileLines, $start, $end - $start + 1);
                $fullFunc = implode("", $fullFuncLines);
                
                $firstBrace = strpos($fullFunc, '{');
                $lastBrace = strrpos($fullFunc, '}');
                $body = substr($fullFunc, $firstBrace + 1, $lastBrace - $firstBrace - 1);
                
                $params = $ref->getParameters();
                $paramName = count($params) > 1 ? $params[1]->getName() : 'params';
                
                $functionsToReplace[$func] = [
                    'body' => $body,
                    'start' => $start,
                    'end' => $end,
                    'paramName' => $paramName,
                    'name' => $func,
                    'fullFunc' => $fullFunc,
                    'firstBrace' => $firstBrace,
                    'lastBrace' => $lastBrace
                ];
            }
        }
    }
    
    if (empty($functionsToReplace)) continue;

    $commandName = 'Admin' . $basename . 'Command';
    $cmdIdentifier = 'admin:' . strtolower($basename);
    
    $commandClass = "<?php\nnamespace SPP\\CLI\\Commands;\n\nuse SPP\\CLI\\Command;\n\nclass {$commandName} extends Command\n{\n";
    $commandClass .= "    protected string \$name = '{$cmdIdentifier}';\n";
    $commandClass .= "    protected string \$description = 'Manage Admin {$basename} operations. Usage: {$cmdIdentifier} <action> [--payload=...] [--json]';\n\n";
    
    // Some commands like admin:config are public, while admin:legacy should be hidden.
    // By default we make it hidden, but we will selectively remove it later.
    $commandClass .= "    public function isHidden(): bool { return true; }\n\n";
    $commandClass .= "    public function execute(array \$args): void\n    {\n";
    $commandClass .= "        \$action = \$this->getArgument(\$args, 0) ?? 'default';\n";
    $commandClass .= "        \$payloadRaw = \$this->getOption(\$args, 'payload', '{}');\n";
    $commandClass .= "        \$payload = json_decode(\$payloadRaw, true) ?: [];\n\n";
    $commandClass .= "        \$methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', \$action)));\n";
    $commandClass .= "        if (method_exists(\$this, \$methodName)) {\n";
    $commandClass .= "            \$this->\$methodName(\$payload, \$args);\n";
    $commandClass .= "        } else {\n";
    $commandClass .= "            \$this->json(['success' => false, 'error' => \"Unknown action: \$action\"], \$args);\n";
    $commandClass .= "        }\n    }\n\n";

    // Build the new proxy methods
    foreach ($functionsToReplace as $funcName => $data) {
        $actionNameRaw = substr($funcName, 5); // remove 'live_'
        $parts = explode('_', strtolower($actionNameRaw));
        if ($parts[0] === strtolower($basename)) {
            array_shift($parts);
        }
        $actionArg = implode('_', $parts);
        if (empty($actionArg)) $actionArg = 'default';

        $methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', $actionArg)));
        $innerBody = $data['body'];
        
        // Transform the parameter variable (e.g. $p to $payload)
        $paramVar = '$' . $data['paramName'];
        if ($paramVar !== '$payload') {
            $innerBody = str_replace($paramVar, '$payload', $innerBody);
        }
        
        // Fix returns
        $innerBody = preg_replace('/return\s+\$la->(.*?);/s', '$la->$1;' . "\n" . '        return;', $innerBody);
        $innerBody = preg_replace('/\$la->setData\((.*?)\);/s', '$this->json($1, $args); return;', $innerBody);
        $innerBody = preg_replace('/\$la->setStatus\([\'"]error[\'"]\)->notify\((.*?)\);/s', '$this->json([\'success\' => false, \'error\' => $1], $args); return;', $innerBody);
        
        // Handle chained methods on notify
        $innerBody = preg_replace('/\$la->notify\((.*?)\)->closeModal\(\)->refresh\(\);/s', '$this->json([\'success\' => true, \'message\' => $1, \'closeModal\' => true, \'refresh\' => true], $args); return;', $innerBody);
        $innerBody = preg_replace('/\$la->notify\((.*?)\)->redirect\((.*?)\);/s', '$this->json([\'success\' => true, \'message\' => $1, \'redirect\' => $2], $args); return;', $innerBody);
        
        $innerBody = preg_replace('/\$la->notify\((.*?)\);/s', '$this->json([\'success\' => true, \'message\' => $1], $args); return;', $innerBody);
        $innerBody = preg_replace('/\$la->setStatus\([\'"]error[\'"]\);/s', '', $innerBody); // Clean up standalone setStatus
        
        $commandClass .= "    private function {$methodName}(array \$payload, array \$args): void {\n";
        $commandClass .= $innerBody . "\n    }\n\n";
    }
    
    $commandClass .= "}\n";
    file_put_contents($commandsDir . '/' . $commandName . '.php', $commandClass);
    
    // Now replace the bodies in the original file
    // We do it backwards so line numbers don't shift!
    usort($functionsToReplace, fn($a, $b) => $b['start'] <=> $a['start']);
    
    foreach ($functionsToReplace as $data) {
        $actionNameRaw = substr($data['name'], 5);
        $parts = explode('_', strtolower($actionNameRaw));
        if ($parts[0] === strtolower($basename)) {
            array_shift($parts);
        }
        $actionArg = implode('_', $parts);
        if (empty($actionArg)) $actionArg = 'default';
        $paramVar = '$' . $data['paramName'];
        
        $proxyBody = "        \$res = \\SPP\\CLI\\CommandManager::execute('{$cmdIdentifier}', ['{$actionArg}', '--payload' => json_encode({$paramVar}), '--json' => '1']);\n";
        $proxyBody .= "        if (\$res['success']) {\n";
        $proxyBody .= "            \$data = json_decode(\$res['output'], true);\n";
        $proxyBody .= "            if (isset(\$data['success']) && !\$data['success']) {\n";
        $proxyBody .= "                \$la->setStatus('error')->notify(\$data['error'] ?? 'Command failed.');\n";
        $proxyBody .= "            } elseif (isset(\$data['modal'])) {\n";
        $proxyBody .= "                \$la->modal(\$data['modal']['title'], \$data['modal']['html'], \$data['modal']['buttons'] ?? []);\n";
        $proxyBody .= "            } elseif (isset(\$data['message'])) {\n";
        $proxyBody .= "                \$la->notify(\$data['message']);\n";
        $proxyBody .= "                if (!empty(\$data['closeModal'])) \$la->closeModal();\n";
        $proxyBody .= "                if (!empty(\$data['refresh'])) \$la->refresh();\n";
        $proxyBody .= "            } else {\n";
        $proxyBody .= "                \$la->setData(\$data ?: []);\n";
        $proxyBody .= "            }\n";
        $proxyBody .= "        } else {\n";
        $proxyBody .= "            \$la->setStatus('error')->notify(\$res['error']);\n";
        $proxyBody .= "        }\n";
        
        $newFunc = substr($data['fullFunc'], 0, $data['firstBrace'] + 1) . "\n" . $proxyBody . "\n" . substr($data['fullFunc'], $data['lastBrace']);
        
        // Splice the array with the new function lines
        $newFuncLines = explode("\n", $newFunc);
        // explode will not keep newlines, but file() keeps newlines. We must add "\n" back to each line except the last one if it didn't have it.
        // Actually, a simpler way is to just join the entire file into a string, do string replacement, and write it back!
        // Wait! What if two functions have identical signatures/bodies? It's better to just do this string replace per function? No, str_replace might replace multiple.
        // Let's just splice lines correctly.
        $newFuncLines = array_map(fn($l) => $l . "\n", explode("\n", rtrim($newFunc, "\n")));
        if (substr($data['fullFunc'], -1) !== "\n") {
            $newFuncLines[count($newFuncLines)-1] = rtrim($newFuncLines[count($newFuncLines)-1], "\n");
        }
        
        array_splice($fileLines, $data['start'], $data['end'] - $data['start'] + 1, $newFuncLines);
    }
    
    file_put_contents($file, implode("", $fileLines));
    echo "Refactored $basename\n";
}
