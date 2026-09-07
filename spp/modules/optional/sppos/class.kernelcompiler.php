<?php
namespace SPPMod\SPPOS;

/**
 * Class KernelCompiler
 * 
 * Aggressively pre-compiles heavy OS configuration (YAML registries, IAM rules)
 * into a single flat PHP array file to eliminate boot overhead in FastCGI environments.
 */
class KernelCompiler
{
    private static $cacheFile = __DIR__ . '/../kernel.cache.php';

    /**
     * Compiles the OS registry into a fast cache file.
     */
    public static function compile(): void
    {
        // 1. Simulate reading heavy YAML files and IAM rules from disk
        $compiledData = [
            'iam_rules' => [
                'wordpress:blog' => ['can_write' => false],
                'magento:store'  => ['can_write' => true],
            ],
            'mesh_routes' => [
                '/shop' => 'magento',
                '/blog' => 'wordpress',
            ],
            'compiled_at' => time()
        ];

        // 2. Write to a flat PHP array file for OpCache preloading
        $content = "<?php\nreturn " . var_export($compiledData, true) . ";\n";
        file_put_contents(self::$cacheFile, $content);
    }

    /**
     * Quickly loads the compiled cache.
     */
    public static function loadFast(): array
    {
        if (!file_exists(self::$cacheFile)) {
            self::compile();
        }
        return require self::$cacheFile;
    }
}
