<?php
$runtimes = [
    'java' => ['name' => 'Java VM', 'path' => null, 'version' => 'N/A'],
    'python' => ['name' => 'Python 3', 'path' => null, 'version' => 'N/A'],
    'node' => ['name' => 'Node.js', 'path' => null, 'version' => 'N/A'],
    'dotnet' => ['name' => '.NET Core', 'path' => null, 'version' => 'N/A'],
    'go' => ['name' => 'Go', 'path' => null, 'version' => 'N/A']
];

$fallbacks = [
    'node' => ['C:\Program Files\nodejs\node.exe'],
    'dotnet' => ['C:\Program Files\dotnet\dotnet.exe'],
    'go' => ['C:\Program Files\Go\bin\go.exe'],
    'python' => ['C:\Python312\python.exe', 'C:\Python311\python.exe', 'C:\Python310\python.exe']
];

$isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

foreach ($runtimes as $id => &$r) {
    $whereCmd = $isWin ? "where $id 2>&1" : "which $id 2>&1";
    $out = [];
    $res = null;
    exec($whereCmd, $out, $res);
    
    if (($res !== 0 || empty($out)) && $isWin && isset($fallbacks[$id])) {
        foreach ($fallbacks[$id] as $fb) {
            if (file_exists($fb)) {
                $out = [$fb];
                $res = 0;
                break;
            }
        }
    }

    if ($res === 0 && !empty($out)) {
        $r['path'] = $out[0];
        
        $exe = escapeshellarg($r['path']);
        if ($id === 'java') {
            $cmd = "$exe -version 2>&1";
        } elseif ($id === 'go') {
            $cmd = "$exe version 2>&1";
        } else {
            $cmd = "$exe --version 2>&1";
        }
        
        $vOut = [];
        $vRes = null;
        exec($cmd, $vOut, $vRes);
        if ($vRes === 0 && !empty($vOut)) {
            $r['version'] = $vOut[0];
        } else {
            $r['version'] = 'Detected';
        }
    }
}
print_r($runtimes);
