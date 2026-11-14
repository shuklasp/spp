<?php

function live_get_codebase_structure($la, $p)
{
    try {
        if (!class_exists('\\SPPMod\\SPPDoc\\DocParser')) {
            $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/spp' : dirname(__DIR__, 2));
            $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2);
            $candidates = [
                $baseDir . '/modules/optional/sppdoc/src/DocParser.php',
                $baseDir . '/modules/spp/sppdoc/src/DocParser.php',
                $appDir . '/spp/modules/optional/sppdoc/src/DocParser.php',
                $appDir . '/spp/modules/spp/sppdoc/src/DocParser.php'
            ];
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    require_once $candidate;
                    break;
                }
            }
            if (!class_exists('\\SPPMod\\SPPDoc\\DocParser')) {
                return $la->setStatus('error')->notify('sppdoc module not found.');
            }
        }

        ob_start();
        $data = \SPPMod\SPPDoc\DocParser::parseCodebase();
        ob_end_clean();
        $la->setData($data);
    } catch (\Throwable $e) {
        if (ob_get_level()) {
            ob_end_clean();
        }
        return $la->setStatus('error')->notify('Error parsing codebase: ' . $e->getMessage());
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
