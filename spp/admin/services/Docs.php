<?php

function live_get_codebase_structure($la, $p)
{
    try {
        if (!class_exists('\\SPPMod\\SPPDoc\\DocParser')) {
            $parserPath = SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/DocParser.php';
            if (file_exists($parserPath)) {
                require_once $parserPath;
            } else {
                return $la->setStatus('error')->notify('sppdoc module not found.');
            }
        }

        $data = \SPPMod\SPPDoc\DocParser::parseCodebase();
        $la->setData($data);
    } catch (\Throwable $e) {
        file_put_contents(SPP_APP_DIR . '/tmp_docs_error.txt', $e->getMessage() . "\n" . $e->getTraceAsString());
        throw $e;
    }
}

function live_get_file_content($la, $p)
{
    try {
        $file = $p['file'] ?? '';
        if (!$file) return $la->setStatus('error')->notify('No file specified.');
        
        // Validate path to prevent directory traversal
        if (str_contains($file, '..')) {
            return $la->setStatus('error')->notify('Invalid file path.');
        }

        $absolutePath = '';
        if (str_starts_with($file, 'spp/')) {
            $absolutePath = SPP_BASE_DIR . substr($file, 3);
        } else {
            $absolutePath = SPP_APP_DIR . '/' . $file;
        }

        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            return $la->setStatus('error')->notify('File not found.');
        }

        $content = file_get_contents($absolutePath);
        $la->setData(['content' => $content]);
    } catch (\Throwable $e) {
        return $la->setStatus('error')->notify('Error reading file: ' . $e->getMessage());
    }
}
