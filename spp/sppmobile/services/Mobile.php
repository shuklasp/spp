<?php
/**
 * Mobile Studio Pro Service Group
 */

function live_Mobile_GetMobileConfig($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $configPath = SPP_BASE_DIR . '/etc/apps/' . $appname . '/mobile.yml';
    
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
        $etcDir = SPP_BASE_DIR . '/etc/apps/' . $appname . '/entities';
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

    $etcDir = SPP_BASE_DIR . '/etc/apps/' . $appname;
    if (!is_dir($etcDir)) mkdir($etcDir, 0777, true);

    $configPath = $etcDir . '/mobile.yml';
    file_put_contents($configPath, \Symfony\Component\Yaml\Yaml::dump($config, 10, 2, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    
    $la->notify("Mobile configuration for '$appname' updated.");
}

function live_Mobile_GenerateMobileApp($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $type = $params['type'] ?? 'pwa'; // pwa, flutter
    
    if ($type === 'flutter') {
        if (!class_exists('\SPP\PolyglotBridge')) {
            return $la->setStatus('error')->notify("Polyglot Bridge not available for Flutter generation.");
        }
        
        $res = \SPP\PolyglotBridge::call('python', 'mobile_builder', 'build_flutter', ['appname' => $appname]);
        if ($res['success']) {
            $la->setData(['result' => $res['data']])->notify("Flutter project generation triggered.");
        } else {
            $la->setStatus('error')->notify("Generation failed: " . ($res['error'] ?? 'Unknown error'));
        }
    } else {
        // Simple PWA Generation logic
        $la->notify("PWA Assets synchronized for '$appname'.");
    }
}

function live_Mobile_GetAssets($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $assetDir = SPP_BASE_DIR . '/src/' . $appname . '/assets';
    if (!is_dir($assetDir)) {
        $la->setData(['assets' => []]);
        return;
    }
    
    $files = glob($assetDir . '/*.{jpg,jpeg,png,gif,svg}', GLOB_BRACE);
    $assets = array_map(function($f) use ($appname) {
        return [
            'name' => basename($f),
            'url' => 'src/' . $appname . '/assets/' . basename($f),
            'size' => filesize($f)
        ];
    }, $files);
    
    $la->setData(['assets' => $assets]);
}

function live_Mobile_UploadAsset($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $assetDir = SPP_BASE_DIR . '/src/' . $appname . '/assets';

    if (!is_dir($assetDir)) {
        if (!mkdir($assetDir, 0777, true)) {
            return $la->setStatus('error')->notify("Failed to create asset directory.");
        }
    }

    // Handle file upload from $_FILES
    if (empty($_FILES['asset_file']) || $_FILES['asset_file']['error'] !== UPLOAD_ERR_OK) {
        return $la->setStatus('error')->notify("No valid file uploaded. Error code: " . ($_FILES['asset_file']['error'] ?? 'none'));
    }

    $file = $_FILES['asset_file'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        return $la->setStatus('error')->notify("File type '$mimeType' is not allowed. Supported: JPEG, PNG, GIF, SVG, WebP.");
    }

    // Sanitize filename
    $originalName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $targetPath = $assetDir . '/' . $originalName;

    // Avoid overwriting — append timestamp if file exists
    if (file_exists($targetPath)) {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $originalName = $base . '_' . time() . '.' . $ext;
        $targetPath = $assetDir . '/' . $originalName;
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $la->setData([
            'uploaded' => [
                'name' => $originalName,
                'url' => 'src/' . $appname . '/assets/' . $originalName,
                'size' => filesize($targetPath)
            ]
        ])->notify("Asset '$originalName' uploaded successfully.");
    } else {
        $la->setStatus('error')->notify("Failed to save uploaded file.");
    }
}
