<?php
namespace SPPMod\SPPDoc;

class SPPEntityDocCollector {
    public function collect() {
        // Ensure codebase is parsed/loaded so all entity classes are declared
        if (class_exists('\\SPPMod\\SPPDoc\\DocParser')) {
            DocParser::parseCodebase();
        }

        $entities = [];
        foreach (get_declared_classes() as $className) {
            if (is_subclass_of($className, '\\SPPMod\\SPPDB\\SPPEntity')) {
                try {
                    $ref = new \ReflectionClass($className);
                    if ($ref->isAbstract()) {
                        continue;
                    }
                    
                    $meta = [];
                    if (method_exists($className, 'getAllMetadata')) {
                        $meta = $className::getAllMetadata();
                    } elseif (method_exists($className, 'getMetadata')) {
                        $meta = [
                            'table' => $className::getMetadata('table'),
                            'attributes' => $className::getMetadata('attributes'),
                            'relations' => $className::getMetadata('relations'),
                        ];
                    }
                    
                    $shortName = $ref->getShortName();
                    $entities[$shortName] = [
                        'class' => $className,
                        'table' => $meta['table'] ?? 'unknown',
                        'attributes' => $meta['attributes'] ?? [],
                        'relations' => $meta['relations'] ?? []
                    ];
                } catch (\Throwable $e) {
                    // Ignore reflection errors
                }
            }
        }
        
        return $entities;
    }
}
