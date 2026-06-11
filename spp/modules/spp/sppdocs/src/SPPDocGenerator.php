<?php
namespace SPPMod\SppDocs;

class SPPDocGenerator {
    
    public function generate() {
        $routes = (new SPPRouteDocCollector())->collect();
        $entities = (new SPPEntityDocCollector())->collect();
        
        $templatePath = __DIR__ . '/../resources/templates/api_doc_template.html';
        if (file_exists($templatePath)) {
            $html = file_get_contents($templatePath);
            $html = str_replace('{{routes}}', json_encode($routes, JSON_PRETTY_PRINT), $html);
            $html = str_replace('{{entities}}', json_encode($entities, JSON_PRETTY_PRINT), $html);
            return $html;
        }
        
        return "Template not found.";
    }
}
