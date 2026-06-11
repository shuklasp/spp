<?php
namespace SPPMod\SppDocs;

class SPPEntityDocCollector {
    public function collect() {
        // Collect entity definitions
        // Currently returning a stub array for demonstration
        return [
            'User' => [
                'id' => 'integer',
                'name' => 'string',
                'email' => 'string',
            ]
        ];
    }
}
