<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class PkgInstallCommand
 * Package manager command for downloading, unpacking, and routing applications.
 */
class PkgInstallCommand extends Command
{
    protected string $name = 'pkg:install';
    protected string $description = 'Download and install a native SPP app or external package from a URL, local path, or central registry';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $source = $this->getArgument($args, 0);
        $appName = $this->getArgument($args, 1);

        if (!$source || !$appName) {
            echo "Usage: php spp.php pkg:install <source_or_name> <app_name> [--type=native|external] [--route=/path]\n";
            echo "Example Native: php spp.php pkg:install my-spp-app school1\n";
            echo "Example External: php spp.php pkg:install https://wordpress.org/latest.zip wordpress --type=external --route=/blog\n";
            return;
        }

        $type = 'native';
        $route = '/' . strtolower($appName);

        foreach ($args as $arg) {
            if (strpos($arg, '--type=') === 0) {
                $type = strtolower(str_replace('--type=', '', $arg));
            } elseif (strpos($arg, '--route=') === 0) {
                $route = '/' . ltrim(str_replace('--route=', '', $arg), '/');
            }
        }

        if (!in_array($type, ['native', 'external'])) {
            echo "[ERROR] Type must be either 'native' or 'external'.\n";
            return;
        }

        // 1. Resolve the source to a physical ZIP file path
        echo "1. Resolving package source: {$source}...\n";
        $zipPath = $this->resolveSource($source);
        if (!$zipPath || !file_exists($zipPath)) {
            echo "[ERROR] Failed to resolve or download package from {$source}\n";
            return;
        }

        // 2. Unpack the package
        echo "2. Unpacking package for {$appName}...\n";
        $tmpDir = SPP_BASE_DIR . '/var/tmp/pkg_install_' . uniqid();
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === TRUE) {
            $zip->extractTo($tmpDir);
            $zip->close();
        } else {
            echo "[ERROR] Failed to open ZIP file at {$zipPath}\n";
            return;
        }

        // Deal with GitHub-style wrapper directories in the ZIP
        $extractedItems = array_diff(scandir($tmpDir), ['.', '..']);
        $extractRoot = $tmpDir;
        if (count($extractedItems) === 1) {
            $firstItem = reset($extractedItems);
            if (is_dir($tmpDir . '/' . $firstItem)) {
                $extractRoot = $tmpDir . '/' . $firstItem;
            }
        }

        // 3. Provision the Application
        echo "3. Provisioning {$type} application: {$appName}...\n";
        if ($type === 'native') {
            $targetDir = SPP_BASE_DIR . '/src/' . $appName;
            $this->moveDir($extractRoot, $targetDir);
            $this->registerNativeApp($appName);
            
            // Execute native app installation hook
            echo "4. Running native SPP site installation...\n";
            $cmd = "php spp.php site:install {$appName}";
            exec($cmd, $output, $returnVar);
            if ($returnVar === 0) {
                echo "   [SUCCESS] Site installation executed successfully.\n";
            } else {
                echo "   [WARNING] Site installation returned non-zero code. You may need to run it manually.\n";
            }

        } else {
            // External App Provisioning (Wordpress, Drupal, etc)
            $publicDir = realpath(SPP_BASE_DIR . '/../public');
            if (!$publicDir) $publicDir = realpath(SPP_BASE_DIR . '/../');
            
            $targetDir = rtrim($publicDir, '/') . $route;
            $this->moveDir($extractRoot, $targetDir);
            
            $this->registerExternalApp($appName, $route, $targetDir);
        }

        // Cleanup
        $this->deleteDir($tmpDir);
        if (strpos($zipPath, 'var/tmp/pkg_dl_') !== false) {
            @unlink($zipPath); // delete downloaded zip
        }

        echo "\n[SUCCESS] Package {$appName} installed successfully!\n";
    }

    /**
     * Resolves the source to a local ZIP file. Downloads if it is a URL or Registry Name.
     */
    private function resolveSource(string $source): ?string
    {
        // 1. Is it a local file?
        if (file_exists($source) && str_ends_with(strtolower($source), '.zip')) {
            return realpath($source);
        }

        // 2. Is it a URL?
        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            return $this->downloadFile($source);
        }

        // 3. Assume it's a Central Registry App name (Mocking GitHub for now)
        // E.g., 'spp-framework/spp-blog-app' -> https://github.com/spp-framework/spp-blog-app/archive/refs/heads/main.zip
        $repoPath = strpos($source, '/') !== false ? $source : "spp-framework/{$source}";
        $url = "https://github.com/{$repoPath}/archive/refs/heads/main.zip";
        
        echo "   [INFO] Querying central registry for '{$source}'... (resolved to {$url})\n";
        return $this->downloadFile($url);
    }

    private function downloadFile(string $url): ?string
    {
        echo "   [INFO] Downloading from {$url}...\n";
        $tmpDir = SPP_BASE_DIR . '/var/tmp';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
        
        $tmpZip = $tmpDir . '/pkg_dl_' . uniqid() . '.zip';
        
        $ch = curl_init($url);
        $fp = fopen($tmpZip, 'w+');
        
        // Follow redirects (crucial for GitHub zips)
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SPP-PackageManager/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        
        if ($success && $httpCode >= 200 && $httpCode < 300) {
            echo "   [SUCCESS] Download completed.\n";
            return $tmpZip;
        }
        
        @unlink($tmpZip);
        return null;
    }

    private function registerNativeApp(string $appName): void
    {
        $settingsPath = SPP_BASE_DIR . '/etc/global-settings.yml';
        if (file_exists($settingsPath)) {
            $yaml = file_get_contents($settingsPath);
            // Check if app already exists
            if (strpos($yaml, "  {$appName}:") === false) {
                // Append it under apps
                $config = "\n  {$appName}:\n    base_url: /{$appName}\n    table_prefix: '{$appName}_'\n    shared_group: core\n    etc_path: ''\n";
                // Inject at the end of the apps section or end of file
                file_put_contents($settingsPath, $config, FILE_APPEND);
                echo "   [SUCCESS] Registered {$appName} in global-settings.yml.\n";
            }
        }
    }

    private function registerExternalApp(string $appName, string $routePath, string $targetDir): void
    {
        // Add to routes.yml Mesh Bypass
        $routerConfigFile = SPP_BASE_DIR . '/etc/routes.yml';
        if (file_exists($routerConfigFile)) {
            $yaml = file_get_contents($routerConfigFile);
            if (strpos($yaml, $routePath) === false) {
                file_put_contents($routerConfigFile, "\n  - path: \"{$routePath}/*\"\n    bypass: true\n", FILE_APPEND);
                echo "   [SUCCESS] External Route bypass injected for {$routePath}.\n";
            }
        }

        // Provision WebOS dummy configuration for integration
        $dummyConfig = "<?php\n// SPP WebOS Auto-Generated Configuration\n";
        $dummyConfig .= "define('DB_HOST', 'spp://kernel');\n";
        $dummyConfig .= "define('DB_USER', 'spp_system');\n";
        $dummyConfig .= "define('SPP_ISOLATION', 'virtual');\n";
        $dummyConfig .= "define('SPP_INSTANCE', '{$appName}:' . md5('{$routePath}'));\n";
        
        file_put_contents($targetDir . '/spp-webos-config.php', $dummyConfig);
        echo "   [SUCCESS] WebOS dummy connection string injected for external mesh app.\n";
    }

    private function moveDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if ($file != '.' && $file != '..') {
                $srcFile = $src . '/' . $file;
                $dstFile = $dst . '/' . $file;
                if (is_dir($srcFile)) {
                    $this->moveDir($srcFile, $dstFile);
                } else {
                    rename($srcFile, $dstFile);
                }
            }
        }
        closedir($dir);
    }
    
    private function deleteDir(string $dirPath): void
    {
        if (!is_dir($dirPath)) return;
        $files = array_diff(scandir($dirPath), ['.','..']);
        foreach ($files as $file) {
            (is_dir("$dirPath/$file")) ? $this->deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
        }
        rmdir($dirPath);
    }
}
