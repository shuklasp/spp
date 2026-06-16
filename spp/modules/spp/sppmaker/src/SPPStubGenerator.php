<?php
namespace SPPMod\SppMaker;

/**
 * Class SPPStubGenerator
 * Provides basic scaffolding for entities, controllers, etc.
 */
class SPPStubGenerator {
    
    public static function generateEntity(string $name, string $moduleDir): bool {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
        $stub = "<?php\nnamespace SPPMod\\" . basename($moduleDir) . ";\n\nuse SPPMod\\SPPEntity\\SppEntity;\n\nclass {$name} extends SppEntity {\n    // Define properties here\n}\n";
        
        $srcDir = $moduleDir . '/src';
        if (!is_dir($srcDir)) {
            mkdir($srcDir, 0777, true);
        }
        
        $filePath = $srcDir . '/' . $name . '.php';
        if (file_exists($filePath)) {
            return false; // Already exists
        }
        
        file_put_contents($filePath, $stub);
        return true;
    }
}
