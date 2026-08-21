<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use DOMDocument;
use DOMXPath;
use Exception;

class OptimizeUXCommand extends Command
{
    protected string $name = 'optimize:ux';
    protected string $description = 'AOT Pre-compile SPP-UX tagged templates to eliminate browser JIT parsing overhead';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = $this->getOption($args, 'app', 'default');
        echo "Starting SPP-UX AOT Optimization for app: {$appname}...\n";

        // Determine base path for the app (simplification for this proof of concept)
        $baseDir = dirname(__DIR__, 3); 
        
        $jsFiles = $this->scanDirectoryForJs("{$baseDir}/spp/modules/spp/drishyam/js");
        // Also scan app specific JS files if needed
        $appDir = "{$baseDir}/src/{$appname}";
        if (is_dir($appDir)) {
            $jsFiles = array_merge($jsFiles, $this->scanDirectoryForJs($appDir));
        }

        $cache = [];
        $marker = '__spp_ux_marker__';

        foreach ($jsFiles as $file) {
            $content = file_get_contents($file);
            $length = strlen($content);
            $i = 0;

            while ($i < $length) {
                // Look for 'html`'
                if ($content[$i] === 'h' && substr($content, $i, 5) === 'html`') {
                    $i += 5;
                    $parts = [];
                    $currentPart = '';
                    $interpDepth = 0;
                    
                    while ($i < $length) {
                        $char = $content[$i];
                        
                        if ($char === '\\') {
                            $currentPart .= $char;
                            $i++;
                            if ($i < $length) $currentPart .= $content[$i];
                        } elseif ($char === '$' && $i + 1 < $length && $content[$i+1] === '{') {
                            if ($interpDepth === 0) {
                                $parts[] = $currentPart;
                                $currentPart = '';
                            }
                            $interpDepth++;
                            $i++;
                        } elseif ($char === '}') {
                            if ($interpDepth > 0) {
                                $interpDepth--;
                            } else {
                                $currentPart .= '}';
                            }
                        } elseif ($char === '`') {
                            if ($interpDepth === 0) {
                                $parts[] = $currentPart;
                                break;
                            } else {
                                $currentPart .= '`';
                            }
                        } else {
                            if ($interpDepth === 0) {
                                $currentPart .= $char;
                            }
                        }
                        $i++;
                    }

                    if (count($parts) <= 1) {
                        $i++;
                        continue;
                    }
                    
                    $hash = implode('$$spp$$', $parts);
                    
                    $html = '';
                    for ($j = 0; $j < count($parts) - 1; $j++) {
                        $html .= $parts[$j];
                        $lastOpen = strrpos($html, '<');
                        $lastClose = strrpos($html, '>');
                        if ($lastOpen !== false && ($lastClose === false || $lastOpen > $lastClose)) {
                            $html .= $marker . $j;
                        } else {
                            $html .= "<!--{$marker}{$j}-->";
                        }
                    }
                    $html .= end($parts);
                    
                    try {
                        $descriptors = $this->computePartDescriptors($html, $marker);
                        $cache[$hash] = [
                            'html' => $html,
                            'parts' => $descriptors
                        ];
                    } catch (Exception $e) {
                        // Skip malformed HTML
                    }
                } else {
                    $i++;
                }
            }
        }

        $cacheFile = "{$baseDir}/public/sppux-cache.js";
        $json = json_encode($cache);
        file_put_contents($cacheFile, "window.__SPP_UX_CACHE__ = {$json};");
        
        echo "AOT Optimization complete. Pre-compiled " . count($cache) . " templates.\n";
        echo "Cache written to: {$cacheFile}\n";
    }
    
    private function computePartDescriptors(string $html, string $marker): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // Wrap in a div to ensure a single root node for reliable XPath
        $dom->loadHTML('<div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $parts = [];
        $xpath = new DOMXPath($dom);
        
        // Find comments
        foreach ($xpath->query('//comment()') as $node) {
            if (strpos($node->nodeValue, $marker) === 0) {
                $index = (int) substr($node->nodeValue, strlen($marker));
                $parts[] = [
                    'type' => 'node',
                    'index' => $index,
                    'path' => $this->getNodePath($node)
                ];
            }
        }
        
        // Find elements with attributes
        foreach ($xpath->query('//*[@*]') as $node) {
            $attrsToRemove = [];
            foreach ($node->attributes as $attr) {
                if (strpos($attr->name, $marker) !== false || strpos($attr->value, $marker) !== false) {
                    $isName = strpos($attr->name, $marker) !== false;
                    $matchStr = $isName ? $attr->name : $attr->value;
                    
                    if (preg_match('/' . preg_quote($marker) . '(\d+)/', $matchStr, $matches)) {
                        $index = (int) $matches[1];
                        $type = 'attr';
                        $name = $attr->name;
                        
                        if ($isName) {
                            $name = preg_replace('/' . preg_quote($marker) . '\d+/', '', $name);
                        }
                        
                        if (strpos($name, '@') === 0 || strpos($name, 'data-spp-evt') === 0) {
                            $type = 'event';
                            $name = strpos($name, '@') === 0 ? substr($name, 1) : str_replace(['data-spp-evt-', 'data-spp-evt'], ['', 'click'], $name);
                        } elseif (strpos($name, '?') === 0) {
                            $type = 'boolean';
                            $name = substr($name, 1);
                        } elseif (strpos($name, '.') === 0) {
                            $type = 'property';
                            $name = substr($name, 1);
                        }
                        
                        $parts[] = [
                            'type' => $type,
                            'name' => $name,
                            'index' => $index,
                            'path' => $this->getNodePath($node)
                        ];
                    }
                }
            }
        }
        
        // Sort parts by index so they align with the values array
        usort($parts, function($a, $b) {
            return $a['index'] <=> $b['index'];
        });
        
        return $parts;
    }
    
    private function getNodePath($node): array
    {
        $path = [];
        $curr = $node;
        // Stop before the top-level '<div>' we injected
        while ($curr->parentNode && $curr->parentNode->nodeName !== '#document') {
            $index = 0;
            $sibling = $curr->previousSibling;
            while ($sibling) {
                $index++;
                $sibling = $sibling->previousSibling;
            }
            $path[] = $index;
            $curr = $curr->parentNode;
        }
        // Remove the injected div from the path
        array_pop($path);
        return array_reverse($path);
    }

    private function scanDirectoryForJs(string $dir): array
    {
        $files = [];
        if (!is_dir($dir)) return $files;
        
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'js') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }
}
