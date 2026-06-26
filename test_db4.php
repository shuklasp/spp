<?php
// Autoloader and SPP setup
spl_autoload_register(function ($class) {
    $prefix = 'SPP\\';
    $base_dir = __DIR__ . '/spp/core/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        $prefixMod = 'SPPMod\\';
        $baseModDir = __DIR__ . '/spp/modules/spp/';
        $lenMod = strlen($prefixMod);
        if (strncmp($prefixMod, $class, $lenMod) === 0) {
            $relative_class = substr($class, $lenMod);
            $parts = explode('\\', $relative_class);
            $modName = strtolower($parts[0]);
            $file = $baseModDir . $modName . '/class.' . strtolower($parts[1]) . '.php';
            if (file_exists($file))
                require $file;
            return;
        }
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

define('SPP_BASE_DIR', __DIR__ . '/spp');
define('SPP_APP_DIR', __DIR__);

try {
    $db = new \SPPMod\SPPDB\SPPDB();
    $res = $db->execute_query("DESCRIBE users");
    print_r($res);
} catch (\Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
