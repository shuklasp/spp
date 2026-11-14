<?php

namespace SPPMod\SPPDoc;

class DocParser
{
    /**
     * Parse the SPP Framework and application codebase using Reflection.
     * @return array
     */
    public static function parseCodebase(bool $includeContent = false): array
    {
        $directories = [
            SPP_BASE_DIR, // spp/core, spp/modules, etc.
            SPP_APP_DIR . '/src' // Application logic
        ];

        $results = [];

        // 1. Load all PHP files to ensure classes are declared and collect config files
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $path = $file->getPathname();
                        $filename = $file->getFilename();
                        
                        // Skip test, temporary, vendor, cache, and dist files
                        if (str_contains($path, 'tests' . SPP_DS) || str_contains($path, 'tmp' . SPP_DS) || str_contains($path, 'vendor' . SPP_DS) || str_contains($path, 'data' . SPP_DS) || str_contains($path, 'cache' . SPP_DS) || str_contains($path, 'dist' . SPP_DS) || str_contains($path, 'node_modules' . SPP_DS)) {
                            continue;
                        }

                        // Skip procedural entry and module initialization scripts
                        if (in_array($filename, ['modinit.php', 'init.php', 'index.php', 'server.php', 'bootstrap.php'], true)) {
                            continue;
                        }

                        $ext = $file->getExtension();
                        
                        // Parse config files
                        if (in_array($ext, ['yml', 'yaml', 'json'])) {
                            $category = self::determineCategory($path, '');
                            if ($category) {
                                $category .= '\\Configurations';
                                if (!isset($results[$category])) {
                                    $results[$category] = [];
                                }
                                
                                $normPath = str_replace('\\', '/', $path);
                                $normApp = str_replace('\\', '/', SPP_APP_DIR);
                                $normBase = str_replace('\\', '/', SPP_BASE_DIR);
                                
                                $relPath = $normPath;
                                if (str_starts_with(strtolower($normPath), strtolower($normApp))) {
                                    $relPath = ltrim(substr($normPath, strlen($normApp)), '/');
                                } elseif (str_starts_with(strtolower($normPath), strtolower($normBase))) {
                                    $relPath = 'spp/' . ltrim(substr($normPath, strlen($normBase)), '/');
                                }
                                
                                $configKey = $category . '\\' . str_replace('/', '\\', $relPath);
                                $results[$category][$configKey] = [
                                    'name' => basename($path), // Use basename for better readability in UI
                                    'type' => 'config',
                                    'file' => $relPath
                                ];
                                if ($includeContent) {
                                    $results[$category][$configKey]['content'] = file_get_contents($path);
                                }
                            }
                            continue;
                        }

                        // Load PHP files
                        if ($ext === 'php') {
                            $isClassFile = str_starts_with($filename, 'class.') 
                                        || str_starts_with($filename, 'interface.') 
                                        || str_starts_with($filename, 'trait.')
                                        || str_starts_with($filename, 'entity.');
                                        
                            $isAppFile = str_contains($path, 'src' . SPP_DS);

                            if (!$isClassFile && !$isAppFile) {
                                continue;
                            }
                            
                            $content = file_get_contents($path);
                            // Prevent loading generic scripts that might redeclare functions or cause side effects
                            if ($isAppFile && !$isClassFile) {
                                if (!preg_match('/^namespace\s+[a-zA-Z0-9_\\\\]+;/m', $content)) {
                                    continue;
                                }
                            }

                            $real = realpath($path);
                            if ($real) {
                                $path = $real;
                            }

                            // Check if all types declared in this file are already loaded in memory
                            $declaredTypes = self::extractDeclaredTypes($content);
                            if (empty($declaredTypes)) {
                                continue;
                            }
                            if (self::hasTopLevelExecution($content)) {
                                continue;
                            }
                            $allAlreadyDeclared = true;
                            foreach ($declaredTypes as $type) {
                                if (!class_exists($type, false) && !interface_exists($type, false) && !trait_exists($type, false) && !(function_exists('enum_exists') && enum_exists($type, false))) {
                                    $allAlreadyDeclared = false;
                                    break;
                                }
                            }
                            if ($allAlreadyDeclared) {
                                continue;
                            }

                            ob_start();
                            try {
                                @include_once $path;
                            } catch (\Throwable $e) {
                                // Ignore
                            }
                            ob_end_clean();
                        }
                    }
                }
            }
        }

        // 2. Reflect on declared classes
        $classes = get_declared_classes();

        foreach ($classes as $className) {
            if (str_contains($className, "@anonymous") || str_contains($className, "\0")) {
                continue;
            }

            try {
                $ref = new \ReflectionClass($className);
            } catch (\ReflectionException $e) {
                continue;
            }
            
            $fileName = $ref->getFileName();

            if (!$fileName || str_contains($fileName, 'vendor' . SPP_DS) || str_contains($fileName, 'tests' . SPP_DS) || str_contains($fileName, 'tmp' . SPP_DS) || str_contains($fileName, 'data' . SPP_DS)) {
                continue; 
            }
            
            $category = self::determineCategory($fileName, $className);
            if (!$category) {
                continue;
            }

            if (!isset($results[$category])) {
                $results[$category] = [];
            }
            $results[$category][$className] = self::parseClass($ref);
        }

        ksort($results);
        return $results;
    }

    private static function determineCategory(string $fileName, string $className): string
    {
        $path = '';
        $normalizedFileName = str_replace('\\', '/', $fileName);

        if (str_contains($normalizedFileName, 'spp/core/') || (str_starts_with($className, 'SPP\\') && !str_starts_with($className, 'SPPMod\\'))) {
            $path = 'Core';
            if (preg_match('/spp\/core\/([^\/]+)\//', $normalizedFileName, $matches)) {
                $subDir = $matches[1];
                if (is_dir(SPP_BASE_DIR . '/core/' . $subDir)) {
                    $path .= '\\' . ucfirst($subDir);
                } else {
                    $path .= '\\' . self::categorizeCoreFile($normalizedFileName, $className);
                }
            } else {
                $path .= '\\' . self::categorizeCoreFile($normalizedFileName, $className);
            }
        } elseif (str_contains($normalizedFileName, 'spp/modules/') || str_starts_with($className, 'SPPMod\\')) {
            if (preg_match('/spp\/modules\/([^\/]+)/', $normalizedFileName, $matches)) {
                $moduleName = $matches[1];
                $path = 'Modules\\' . ucfirst($moduleName);
                if (preg_match('/spp\/modules\/' . preg_quote($moduleName, '/') . '\/([^\/]+)\//', $normalizedFileName, $subMatches)) {
                    if (is_dir(SPP_BASE_DIR . '/modules/' . $moduleName . '/' . $subMatches[1])) {
                        $path .= '\\' . ucfirst($subMatches[1]);
                    }
                }
            } else {
                $path = 'Modules\\General';
            }
        } elseif (str_contains($normalizedFileName, '/src/') || str_starts_with($className, 'App\\')) {
            if (preg_match('/\/src\/([^\/]+)/', $normalizedFileName, $matches)) {
                $appName = $matches[1];
                $appDir = SPP_APP_DIR . '/src/' . $appName;
                $realAppDir = realpath($appDir);
                if ($realAppDir && is_dir($realAppDir)) {
                    $appName = basename($realAppDir);
                    $appDir = $realAppDir;
                }
                
                if (is_dir($appDir . '/components') || is_dir($appDir . '/pages') || file_exists($appDir . '/app.json') || is_dir($appDir . '/serv') || file_exists($appDir . '/etc/events.yml')) {
                    $subPath = 'General';
                    if (preg_match('/\/src\/' . preg_quote($appName, '/') . '\/([^\/]+)/i', $normalizedFileName, $subMatches)) {
                        $subPath = ucfirst($subMatches[1]);
                    }
                    $path = 'Apps\\' . $appName . '\\' . $subPath;
                } else {
                    $path = 'Shared\\' . $appName;
                }
            } else {
                $path = 'Apps\\General';
            }
        }

        return $path;
    }

    private static function categorizeCoreFile(string $normalizedFileName, string $className): string
    {
        $baseName = basename($normalizedFileName);
        $nameLower = strtolower($baseName);
        
        if (str_starts_with($nameLower, 'int.')) {
            return 'Interfaces';
        }
        if (str_contains($nameLower, 'event') || str_contains($nameLower, 'listener')) {
            return 'Events';
        }
        if (str_contains($nameLower, 'command')) {
            return 'CLI';
        }
        if (str_contains($nameLower, 'db') || str_contains($nameLower, 'migration')) {
            return 'Database';
        }
        if (str_contains($nameLower, 'module')) {
            return 'Modules';
        }
        if (str_contains($nameLower, 'polyglot') || str_contains($nameLower, 'translation')) {
            return 'Translation';
        }
        if (str_contains($nameLower, 'session') || str_contains($nameLower, 'registry') || str_contains($nameLower, 'xsettings') || str_contains($nameLower, 'container') || str_contains($nameLower, 'stack')) {
            return 'State';
        }
        if (str_contains($nameLower, 'error') || str_contains($nameLower, 'exception')) {
            return 'Exceptions';
        }
        if (str_contains($nameLower, 'scheduler') || str_contains($nameLower, 'cron')) {
            return 'Scheduler';
        }
        if (str_contains($nameLower, 'utils') || str_contains($nameLower, 'string') || str_contains($nameLower, 'fs') || str_contains($nameLower, 'xml') || str_contains($nameLower, 'debug')) {
            return 'Utilities';
        }
        if (str_contains($nameLower, 'app') || str_contains($nameLower, 'sppbase') || str_contains($nameLower, 'sppconfig') || str_contains($nameLower, 'sppglobal') || str_contains($nameLower, 'versionmanager') || str_contains($nameLower, 'sppobject')) {
            return 'Foundation';
        }
        if (str_contains($nameLower, 'response') || str_contains($nameLower, 'pipeline') || str_contains($nameLower, 'resourcecontroller')) {
            return 'Http';
        }
        
        return 'Base';
    }

    private static function parseClass(\ReflectionClass $ref): array
    {
        $normPath = str_replace('\\', '/', $ref->getFileName());
        $normApp = str_replace('\\', '/', SPP_APP_DIR);
        $normBase = str_replace('\\', '/', SPP_BASE_DIR);
        
        $relPath = $normPath;
        if (str_starts_with(strtolower($normPath), strtolower($normApp))) {
            $relPath = ltrim(substr($normPath, strlen($normApp)), '/');
        } elseif (str_starts_with(strtolower($normPath), strtolower($normBase))) {
            $relPath = 'spp/' . ltrim(substr($normPath, strlen($normBase)), '/');
        }

        $data = [
            'name' => $ref->getShortName(),
            'namespace' => $ref->getNamespaceName(),
            'type' => $ref->isInterface() ? 'interface' : ($ref->isTrait() ? 'trait' : ($ref->isAbstract() ? 'abstract class' : 'class')),
            'is_final' => $ref->isFinal(),
            'parent' => $ref->getParentClass() ? $ref->getParentClass()->getName() : null,
            'interfaces' => $ref->getInterfaceNames(),
            'traits' => $ref->getTraitNames(),
            'docblock' => self::formatDocBlock($ref->getDocComment()),
            'file' => $relPath,
            'constants' => [],
            'properties' => [],
            'methods' => []
        ];

        // Constants
        foreach ($ref->getReflectionConstants() as $const) {
            $inherited_from = $const->getDeclaringClass()->getName() !== $ref->getName() ? $const->getDeclaringClass()->getName() : null;
            $data['constants'][] = [
                'name' => $const->getName(),
                'value' => var_export($const->getValue(), true),
                'visibility' => $const->isPublic() ? 'public' : ($const->isProtected() ? 'protected' : 'private'),
                'docblock' => self::formatDocBlock($const->getDocComment()),
                'inherited_from' => $inherited_from
            ];
        }

        // Properties
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $prop) {
            $inherited_from = $prop->getDeclaringClass()->getName() !== $ref->getName() ? $prop->getDeclaringClass()->getName() : null;
            
            $type = $prop->getType();
            $data['properties'][] = [
                'name' => $prop->getName(),
                'type' => $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed',
                'visibility' => $prop->isPublic() ? 'public' : 'protected',
                'static' => $prop->isStatic(),
                'docblock' => self::formatDocBlock($prop->getDocComment()),
                'inherited_from' => $inherited_from
            ];
        }

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
            $inherited_from = $method->getDeclaringClass()->getName() !== $ref->getName() ? $method->getDeclaringClass()->getName() : null;

            $params = [];
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                $params[] = [
                    'name' => $param->getName(),
                    'type' => $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed',
                    'optional' => $param->isOptional()
                ];
            }

            $data['methods'][] = [
                'name' => $method->getName(),
                'visibility' => $method->isPublic() ? 'public' : 'protected',
                'static' => $method->isStatic(),
                'docblock' => self::formatDocBlock($method->getDocComment()),
                'parameters' => $params,
                'return_type' => $method->getReturnType() instanceof \ReflectionNamedType ? $method->getReturnType()->getName() : 'mixed',
                'inherited_from' => $inherited_from
            ];
        }

        return $data;
    }

    private static function formatDocBlock($doc): string
    {
        if (!$doc) return '';
        $doc = preg_replace('#^/\*\*\s*#', '', $doc);
        $doc = preg_replace('#\s*\*/$#', '', $doc);
        $doc = preg_replace('#^\s*\*\s?#m', '', $doc);
        return trim($doc);
    }

    private static function extractDeclaredTypes(string $content): array
    {
        $types = [];
        try {
            $tokens = token_get_all($content);
            $namespace = '';
            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                if (is_array($tokens[$i]) && $tokens[$i][0] === T_NAMESPACE) {
                    $ns = '';
                    for ($j = $i + 1; $j < $count; $j++) {
                        if ($tokens[$j] === ';' || $tokens[$j] === '{') {
                            break;
                        }
                        if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR])) {
                            $ns .= $tokens[$j][1];
                        }
                    }
                    $namespace = trim($ns);
                    continue;
                }

                if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, defined('T_ENUM') ? T_ENUM : -1])) {
                    // Check previous non-whitespace token: must not be :: (T_DOUBLE_COLON)
                    $prevIdx = $i - 1;
                    while ($prevIdx >= 0 && is_array($tokens[$prevIdx]) && in_array($tokens[$prevIdx][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                        $prevIdx--;
                    }
                    if ($prevIdx >= 0 && is_array($tokens[$prevIdx]) && $tokens[$prevIdx][0] === T_DOUBLE_COLON) {
                        continue; // Skip Foo::class references
                    }

                    // Next non-whitespace token MUST be the type name (T_STRING)
                    $nextIdx = $i + 1;
                    while ($nextIdx < $count && is_array($tokens[$nextIdx]) && in_array($tokens[$nextIdx][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                        $nextIdx++;
                    }

                    if ($nextIdx < $count && is_array($tokens[$nextIdx]) && $tokens[$nextIdx][0] === T_STRING) {
                        $typeName = $tokens[$nextIdx][1];
                        $types[] = $namespace ? $namespace . '\\' . $typeName : $typeName;
                    }
                }
            }
        } catch (\Throwable $e) {}
        return $types;
    }

    private static function hasTopLevelExecution(string $content): bool
    {
        try {
            $tokens = token_get_all($content);
            $braceLevel = 0;
            $parenLevel = 0;
            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];
                if ($token === '{' || (is_array($token) && $token[0] === T_CURLY_OPEN)) {
                    $braceLevel++;
                    continue;
                }
                if ($token === '}') {
                    if ($braceLevel > 0) {
                        $braceLevel--;
                    }
                    continue;
                }
                if ($token === '(') {
                    $parenLevel++;
                    continue;
                }
                if ($token === ')') {
                    if ($parenLevel > 0) {
                        $parenLevel--;
                    }
                    continue;
                }
                if ($braceLevel === 0 && is_array($token)) {
                    if (in_array($token[0], [T_ECHO, T_PRINT, T_EXIT], true)) {
                        return true;
                    }
                    if ($parenLevel === 0 && $token[0] === T_VARIABLE) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {}
        return false;
    }
}
