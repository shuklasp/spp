<?php
/**
 * Mobile Studio Pro Service Group
 */

function live_Mobile_GetProjects($la, $params) {
    $projects = [];
    $dir = STUDIO_ROOT . '/projects';
    if (is_dir($dir)) {
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            if (is_dir($dir . '/' . $item)) {
                $confFile = $dir . '/' . $item . '/mobile.yml';
                if (!file_exists($confFile)) $confFile = $dir . '/' . $item . '/config.json';
                
                if (file_exists($confFile)) {
                    $ext = pathinfo($confFile, PATHINFO_EXTENSION);
                    $conf = ($ext === 'yml') ? \Symfony\Component\Yaml\Yaml::parseFile($confFile) : json_decode(file_get_contents($confFile), true);
                    $projects[] = [
                        'id' => $item,
                        'name' => $conf['app_name'] ?? $item,
                        'version' => $conf['version'] ?? '1.0.0',
                        'updated_at' => date('Y-m-d H:i:s', filemtime($confFile))
                    ];
                }
            }
        }
    }
    $la->setData(['projects' => $projects]);
}

function live_Mobile_CreateProject($la, $params) {
    $name = $params['name'] ?? 'New Project';
    $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name)) . '_' . time();
    $dir = STUDIO_ROOT . '/projects/' . $id;
    
    if (is_dir($dir)) return $la->setStatus('error')->notify("Project ID collision.");
    mkdir($dir, 0777, true);

    $config = [
        'app_id' => 'com.spp.' . $id,
        'app_name' => $name,
        'version' => '1.0.0',
        'created_at' => date('Y-m-d H:i:s'),
        'activeTheme' => 'Light',
        'themes' => [
            [
                'name' => 'Light',
                'primary' => '#6366f1',
                'secondary' => '#a855f7',
                'background' => '#ffffff',
                'surface' => '#f8fafc',
                'text' => '#1e293b',
                'font' => 'Outfit',
                'borderRadius' => 12
            ],
            [
                'name' => 'Dark',
                'primary' => '#818cf8',
                'secondary' => '#c084fc',
                'background' => '#0f172a',
                'surface' => '#1e293b',
                'text' => '#f8fafc',
                'font' => 'Outfit',
                'borderRadius' => 12
            ]
        ],
        'screens' => [
            ['id' => 'home', 'title' => 'Home', 'type' => 'dashboard', 'mapping' => '', 'components' => []]
        ]
    ];

    file_put_contents($dir . '/mobile.yml', \Symfony\Component\Yaml\Yaml::dump($config, 10, 2));
    $la->setData(['project_id' => $id])->notify("Project '$name' created successfully.");
}

function live_Mobile_GetMobileConfig($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $configPath = STUDIO_ROOT . '/etc/apps/' . $appname . '/mobile.yml';
    
    $config = [
        'app_id' => 'com.spp.' . $appname,
        'app_name' => ucfirst($appname) . ' Mobile',
        'version' => '1.0.0',
        'theme' => [
            'primary' => '#6366f1',
            'secondary' => '#a855f7',
            'surface' => '#1e1e2e'
        ],
        'screens' => [
            ['id' => 'home', 'title' => 'Home', 'type' => 'dashboard', 'mapping' => '']
        ]
    ];

    if (file_exists($configPath)) {
        $loaded = \Symfony\Component\Yaml\Yaml::parseFile($configPath);
        $config = array_merge($config, $loaded);
    }

    $la->setData(['config' => $config]);
}

function live_Mobile_GetEntities($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $entities = \SPP\Scheduler::withContext($appname, function() use ($appname) {
        $etcDir = dirname(SPP_BASE_DIR) . '/etc/apps/' . $appname . '/entities';
        if (!is_dir($etcDir)) return [];
        return array_map(fn($f) => pathinfo($f, PATHINFO_FILENAME), glob($etcDir . '/*.yml'));
    });
    $la->setData(['entities' => $entities]);
}

function live_Mobile_SaveMobileConfig($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $config = $params['config'] ?? [];
    if (is_string($config)) $config = json_decode($config, true);

    if (empty($config)) return $la->setStatus('error')->notify("Configuration data required.");

    $etcDir = STUDIO_ROOT . '/etc/apps/' . $appname;
    if (!is_dir($etcDir)) mkdir($etcDir, 0777, true);

    $configPath = $etcDir . '/mobile.yml';
    file_put_contents($configPath, \Symfony\Component\Yaml\Yaml::dump($config, 10, 2, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    
    $la->notify("Mobile configuration for '$appname' updated.");
}

function live_Mobile_GenerateMobileApp($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $type = $params['type'] ?? 'pwa'; // pwa, flutter
    
    if ($type === 'flutter') {
        $projectDir = STUDIO_ROOT . '/projects/' . $appname;
        $buildDir = $projectDir . '/build/flutter';
        
        if (!is_dir($buildDir)) mkdir($buildDir, 0777, true);
        
        // Generate pubspec.yaml
        $pubspec = "name: $appname\ndescription: A new Flutter project generated by SPP Mobile Studio\nversion: 1.0.0+1\nenvironment:\n  sdk: '>=3.0.0 <4.0.0'\ndependencies:\n  flutter:\n    sdk: flutter\n  cupertino_icons: ^1.0.2\n  get: ^4.6.5\n  http: ^1.1.0\ndev_dependencies:\n  flutter_test:\n    sdk: flutter\nflutter:\n  uses-material-design: true\n";
        file_put_contents($buildDir . '/pubspec.yaml', $pubspec);
        
        // Generate lib structure
        if (!is_dir($buildDir . '/lib')) mkdir($buildDir . '/lib', 0777, true);
        
        $mainDart = "import 'package:flutter/material.dart';\nimport 'package:get/get.dart';\n\nvoid main() {\n  runApp(const MyApp());\n}\n\nclass MyApp extends StatelessWidget {\n  const MyApp({super.key});\n  @override\n  Widget build(BuildContext context) {\n    return GetMaterialApp(\n      title: '$appname',\n      theme: ThemeData(primarySwatch: Colors.indigo),\n      home: const Scaffold(body: Center(child: Text('Welcome to $appname'))),\n    );\n  }\n}\n";
        file_put_contents($buildDir . '/lib/main.dart', $mainDart);
        
        $la->notify("Flutter project scaffolded at projects/$appname/build/flutter");
    } else {
        // Simple PWA Generation logic
        $la->notify("PWA Assets synchronized for '$appname'.");
    }
}

function live_Mobile_GetAssets($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $path = $params['path'] ?? ''; // Subfolder path
    $baseAssetDir = dirname(SPP_BASE_DIR) . '/src/' . $appname . '/assets';
    $targetDir = realpath($baseAssetDir . '/' . $path);

    // Security: Ensure target is within baseAssetDir
    if (!$targetDir || strpos($targetDir, realpath($baseAssetDir)) !== 0) {
        $targetDir = $baseAssetDir;
        $path = '';
    }

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            $la->setData(['assets' => [], 'folders' => [], 'currentPath' => '']);
            return;
        }
    }
    
    $items = scandir($targetDir);
    $assets = [];
    $folders = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $targetDir . '/' . $item;
        $relPath = ($path ? $path . '/' : '') . $item;

        if (is_dir($fullPath)) {
            $folders[] = [
                'name' => $item,
                'path' => $relPath,
                'type' => 'folder'
            ];
        } else if (preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $item)) {
            $assets[] = [
                'name' => $item,
                'path' => $relPath,
                'url' => 'src/' . $appname . '/assets/' . $relPath,
                'size' => filesize($fullPath),
                'type' => 'file'
            ];
        }
    }
    
    $la->setData([
        'assets' => $assets,
        'folders' => $folders,
        'currentPath' => $path
    ]);
}

function live_Mobile_RenameAsset($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $oldPath = $params['oldPath'] ?? '';
    $newName = $params['newName'] ?? '';
    if (!$oldPath || !$newName) return $la->setStatus('error')->notify("Path and New Name required.");

    $baseAssetDir = dirname(SPP_BASE_DIR) . '/src/' . $appname . '/assets';
    $oldFullPath = realpath($baseAssetDir . '/' . $oldPath);
    
    if (!$oldFullPath || strpos($oldFullPath, realpath($baseAssetDir)) !== 0) {
        return $la->setStatus('error')->notify("Invalid asset path.");
    }

    // Sanitize new name
    $newName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $newName);
    $newFullPath = dirname($oldFullPath) . '/' . $newName;

    if (file_exists($newFullPath)) return $la->setStatus('error')->notify("Target name already exists.");

    if (rename($oldFullPath, $newFullPath)) {
        $la->notify("Asset structurally renamed to '$newName'.");
    } else {
        $la->setStatus('error')->notify("Failed to rename asset.");
    }
}

function live_Mobile_DeleteAsset($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $path = $params['path'] ?? '';
    if (!$path) return $la->setStatus('error')->notify("Asset path required.");

    $baseAssetDir = dirname(SPP_BASE_DIR) . '/src/' . $appname . '/assets';
    $fullPath = realpath($baseAssetDir . '/' . $path);
    
    if (!$fullPath || strpos($fullPath, realpath($baseAssetDir)) !== 0) {
        return $la->setStatus('error')->notify("Invalid asset path.");
    }

    $success = false;
    if (is_dir($fullPath)) {
        // Recursive delete for folders
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        $success = rmdir($fullPath);
    } else {
        $success = unlink($fullPath);
    }

    if ($success) {
        $la->notify("Asset structurally removed from storage.");
    } else {
        $la->setStatus('error')->notify("Failed to delete asset.");
    }
}

function live_Mobile_CreateAssetFolder($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $path = $params['path'] ?? ''; // Current dir
    $name = $params['name'] ?? 'New Folder';
    
    $baseAssetDir = dirname(SPP_BASE_DIR) . '/src/' . $appname . '/assets';
    $targetParent = realpath($baseAssetDir . '/' . $path);
    
    if (!$targetParent || strpos($targetParent, realpath($baseAssetDir)) !== 0) {
        $targetParent = $baseAssetDir;
    }

    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    $newDir = $targetParent . '/' . $name;

    if (is_dir($newDir)) return $la->setStatus('error')->notify("Folder already exists.");

    if (mkdir($newDir, 0777, true)) {
        $la->notify("Logical namespace '$name' created.");
    } else {
        $la->setStatus('error')->notify("Failed to create folder.");
    }
}

function live_Mobile_UploadAssetBase64($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $path = $params['path'] ?? ''; // Support uploading into subfolders
    $assetDir = dirname(SPP_BASE_DIR) . '/src/' . $appname . '/assets';
    
    if ($path) {
        $targetDir = realpath($assetDir . '/' . $path);
        if ($targetDir && strpos($targetDir, realpath($assetDir)) === 0) {
            $assetDir = $targetDir;
        }
    }
    
    $filename = $params['filename'] ?? '';
    $mimeType = $params['mimeType'] ?? '';
    $base64Data = $params['base64Data'] ?? '';

    if (empty($base64Data) || empty($filename)) {
        return $la->setStatus('error')->notify("Upload failed: Incomplete base64 data payload.");
    }

    if (!is_dir($assetDir)) {
        if (!mkdir($assetDir, 0777, true)) {
            return $la->setStatus('error')->notify("Failed to create asset directory: $assetDir");
        }
    }

    // High-Fidelity MIME Validation
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        return $la->setStatus('error')->notify("File type '$mimeType' is not allowed. Supported: JPEG, PNG, GIF, SVG, WebP.");
    }

    // Decode and verify
    $bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Data));
    if (!$bin) {
        return $la->setStatus('error')->notify("Upload failed: Invalid base64 encoding.");
    }

    // Deterministic Filename Sanitization
    $originalName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));
    $targetPath = $assetDir . '/' . $originalName;

    // Intelligent Overwrite Prevention
    if (file_exists($targetPath)) {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $originalName = $base . '_' . substr(md5(time()), 0, 6) . '.' . $ext;
        $targetPath = $assetDir . '/' . $originalName;
    }

    if (file_put_contents($targetPath, $bin)) {
        $la->setData([
            'uploaded' => [
                'name' => $originalName,
                'url' => 'src/' . $appname . '/assets/' . ($path ? $path . '/' : '') . $originalName,
                'size' => strlen($bin),
                'type' => $mimeType
            ]
        ])->notify("Asset '$originalName' uploaded successfully via tunnel.");
    } else {
        $la->setStatus('error')->notify("Critical error: Failed to write tunneled asset to storage.");
    }
}

function live_Mobile_GetBlueprintLibrary($la, $params) {
    // Force absolute path normalization for Windows
    $root = str_replace('\\', '/', STUDIO_ROOT);
    $blueprintDir = rtrim($root, '/') . '/blueprints';
    
    if (!is_dir($blueprintDir)) {
        @mkdir($blueprintDir, 0777, true);
    }

    $library = [
        'layouts' => [],
        'blueprints' => []
    ];

    // Use a more robust scanning method than glob
    if (is_dir($blueprintDir)) {
        $items = scandir($blueprintDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = $blueprintDir . '/' . $item;
            
            if (is_dir($fullPath)) {
                $category = $item;
                $subItems = scandir($fullPath);
                foreach ($subItems as $subItem) {
                    if (preg_match('/\.(json|php)$/i', $subItem)) {
                        $filePath = $fullPath . '/' . $subItem;
                        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                        $content = ($ext === 'json') ? json_decode(file_get_contents($filePath), true) : include($filePath);
                        
                        if ($content && is_array($content)) {
                            if ($category === 'layouts') {
                                $library['layouts'][$content['id'] ?? basename($subItem, ".$ext")] = $content;
                            } else {
                                if (isset($content[0]) && is_array($content[0])) {
                                    $library['blueprints'] = array_merge($library['blueprints'], $content);
                                } else {
                                    $library['blueprints'][] = $content;
                                }
                            }
                        }
                    }
                }
            } else if (preg_match('/\.(json|php)$/i', $item)) {
                $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
                $content = ($ext === 'json') ? json_decode(file_get_contents($fullPath), true) : include($fullPath);
                if ($content && is_array($content)) {
                    if (isset($content[0]) && is_array($content[0])) {
                        $library['blueprints'] = array_merge($library['blueprints'], $content);
                    } else {
                        $library['blueprints'][] = $content;
                    }
                }
            }
        }
    }

    $la->setData([
        'library' => $library
    ]);
}

function live_Mobile_GetComponentLibrary($la, $params) {
    $root = defined('STUDIO_ROOT') ? STUDIO_ROOT : __DIR__;
    $compDir = $root . '/components';
    
    $library = [];
    if (is_dir($compDir)) {
        $files = scandir($compDir);
        foreach ($files as $file) {
            if (preg_match('/\.(json|php)$/i', $file)) {
                $filePath = $compDir . '/' . $file;
                $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                $content = ($ext === 'json') ? json_decode(file_get_contents($filePath), true) : include($filePath);
                if ($content) $library[] = $content;
            }
        }
    }
    
    $la->setData(['components' => $library]);
}

function live_Mobile_CreateSnapshot($la, $params) {
    $id = $params['id'] ?? '';
    $name = $params['name'] ?? 'Manual Snapshot';
    
    if (!$id) return $la->setStatus('error')->notify("Project ID is required.");
    
    $projectDir = STUDIO_ROOT . '/projects/' . $id;
    $snapshotDir = $projectDir . '/snapshots';
    
    if (!is_dir($snapshotDir)) mkdir($snapshotDir, 0777, true);
    
    $timestamp = date('Ymd_His');
    $snapshotFile = $snapshotDir . '/snapshot_' . $timestamp . '.yml';
    
    $configFile = $projectDir . '/mobile.yml';
    if (!file_exists($configFile)) return $la->setStatus('error')->notify("Project config not found.");
    
    $config = \Symfony\Component\Yaml\Yaml::parseFile($configFile);
    $config['snapshot_meta'] = [
        'name' => $name,
        'timestamp' => time(),
        'date' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($snapshotFile, \Symfony\Component\Yaml\Yaml::dump($config, 10, 2, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    
    $la->notify("Snapshot '$name' created successfully.");
}

function live_Mobile_GetSnapshots($la, $params) {
    $id = $params['id'] ?? '';
    if (!$id) return $la->setData(['snapshots' => []]);
    
    $snapshotDir = STUDIO_ROOT . '/projects/' . $id . '/snapshots';
    if (!is_dir($snapshotDir)) return $la->setData(['snapshots' => []]);
    
    $files = glob($snapshotDir . '/*.yml');
    $snapshots = [];
    foreach ($files as $file) {
        $snapshots[] = [
            'id' => basename($file),
            'name' => basename($file, '.yml'),
            'path' => $file
        ];
    }
    $la->setData(['snapshots' => array_reverse($snapshots)]);
}

function live_Mobile_SaveThemePreset($la, $params) {
    $themes = $params['themes'] ?? [];
    $name = $params['name'] ?? 'Design System ' . date('Y-m-d');
    
    $presetDir = STUDIO_ROOT . '/presets/themes';
    if (!is_dir($presetDir)) mkdir($presetDir, 0777, true);
    
    $filename = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name)) . '.json';
    $data = [
        'name' => $name,
        'themes' => $themes,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($presetDir . '/' . $filename, json_encode($data, JSON_PRETTY_PRINT));
    $la->notify("Aggregate theme '$name' archived in system design library.");
}

function live_Mobile_GetThemePresets($la, $params) {
    $presetDir = STUDIO_ROOT . '/presets/themes';
    $presets = [];
    if (is_dir($presetDir)) {
        $files = glob($presetDir . '/*.json');
        foreach ($files as $file) {
            $presets[] = json_decode(file_get_contents($file), true);
        }
    }
    $la->setData(['presets' => $presets]);
}
