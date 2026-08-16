<?php
namespace SPPMod\Lekhak\Core;

/**
 * Class DocGen
 * Generates LekhakNodes by reflecting on SPP Core and Modules.
 */
class DocGen
{
    protected string $baseDir;
    protected string $version;

    public function __construct(string $version = '1.0')
    {
        $this->baseDir = SPP_BASE_DIR;
        $this->version = $version;
    }

    /**
     * Scan a directory and generate documentation nodes.
     */
    public function generateFromDir(string $dir, string $category = 'API'): array
    {
        $nodes = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $phpFiles = new \RegexIterator($files, '/\.php$/');

        foreach ($phpFiles as $file) {
            $path = $file->getRealPath();
            $content = file_get_contents($path);
            
            // Basic check for class definition
            if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $matches)) {
                $className = $matches[1];
                
                // Get namespace
                $namespace = '';
                if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)/', $content, $nsMatches)) {
                    $namespace = $nsMatches[1];
                }

                $fullClass = $namespace ? $namespace . '\\' . $className : $className;
                echo "Found class: $fullClass in $path\n";
                
                // Ensure the class is loaded
                if (!class_exists($fullClass, true)) {
                    echo "  Could not load class: $fullClass\n";
                    continue;
                }

                try {
                    $node = $this->generateNodeForClass($fullClass, $category);
                    if ($node) {
                        echo "  Generated node for $fullClass\n";
                        $nodes[] = $node;
                    }
                } catch (\Throwable $e) {
                    echo "  Error reflecting $fullClass: " . $e->getMessage() . "\n";
                    continue;
                }
            }
        }
        return $nodes;
    }

    protected function generateNodeForClass(string $className, string $category): ?LekhakNode
    {
        if (!class_exists($className)) return null;

        $reflection = new \ReflectionClass($className);
        $docComment = $reflection->getDocComment() ?: 'No documentation available.';
        
        // Clean up doc comment
        $docComment = preg_replace('/^\/\*\*|\*\/|\n\s*\* ?/m', "\n", $docComment);

        $node = new LekhakNode();
        $node->title = $className;
        $node->langcode = 'en';
        $node->save();
        $node->applyTransition('published');
        
        $body = "<h2>Class: {$className}</h2>";
        $body .= "<div class='doc-comment'>" . nl2br(htmlspecialchars($docComment)) . "</div>";
        
        $body .= "<h3>Methods</h3><ul>";
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodDoc = $method->getDocComment() ?: '';
            $methodDoc = preg_replace('/^\/\*\*|\*\/|\n\s*\* ?/m', "\n", $methodDoc);
            
            $body .= "<li><strong>{$method->getName()}()</strong>";
            if ($methodDoc) {
                $body .= "<p class='method-doc'>" . nl2br(htmlspecialchars($methodDoc)) . "</p>";
            }
            $body .= "</li>";
        }
        $body .= "</ul>";

        $node->body = $body;
        
        // Metadata for Lekhak
        $node->setMetadata('category', $category);
        $node->setMetadata('version', $this->version);
        $node->setMetadata('alias', "spp/{$this->version}/api/" . str_replace('\\', '/', $className));

        return $node;
    }
}
