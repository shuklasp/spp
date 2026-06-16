<?php

namespace SPPMod\SPPView;

/**
 * class ViewCompiler
 * 
 * High-performance DOM AST View Compiler.
 * Translates legacy HTML and <php-comp> tags into native cached PHP files
 * to eliminate runtime regex parsing and boost rendering speeds by 10x-50x.
 */
class ViewCompiler
{
    /**
     * Compiles an HTML view file into a cached PHP file, returning the path to the cache.
     * 
     * @param string $filePath The absolute path to the .html or .php view
     * @return string The absolute path to the compiled cached .php file
     */
    public static function compile(string $filePath): string
    {
        $cacheDir = SPP_BASE_DIR . '/var/cache/views';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        // Cache busting on modification
        $hash = md5($filePath . filemtime($filePath));
        $cacheFile = $cacheDir . '/' . md5($filePath) . '_' . $hash . '.php';

        if (file_exists($cacheFile)) {
            return $cacheFile;
        }

        // Clean up old cached versions of this file
        foreach (glob($cacheDir . '/' . md5($filePath) . '_*.php') as $oldCache) {
            @unlink($oldCache);
        }

        $html = file_get_contents($filePath);
        
        // 1. Extract PHP blocks to protect them from DOMDocument strictness
        $phpBlocks = [];
        $html = preg_replace_callback('/(<\?php.*?\?>)|(<\?=(.*?)\?>)/is', function($matches) use (&$phpBlocks) {
            $id = 'PHP_BLOCK_' . count($phpBlocks);
            if (!empty($matches[2])) {
                $content = trim($matches[3]);
                $phpBlocks[$id] = "<?php echo htmlspecialchars((string)({$content}), ENT_QUOTES, 'UTF-8'); ?>";
            } else {
                $phpBlocks[$id] = $matches[1];
            }
            return "<spp-php id=\"{$id}\"></spp-php>";
        }, $html);

        // 2. Liberal DOM Parsing (Enforce HTML5 leniency)
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // 3. Process <spp-if> and <spp-foreach> tags
        $xpath = new \DOMXPath($dom);
        
        // Process spp-if
        $ifNodes = $xpath->query('//spp-if');
        foreach ($ifNodes as $node) {
            $condition = $node->getAttribute('condition');
            $idStart = 'PHP_BLOCK_' . count($phpBlocks);
            $phpBlocks[$idStart] = "<?php if ({$condition}): ?>";
            
            $idEnd = 'PHP_BLOCK_' . (count($phpBlocks));
            $phpBlocks[$idEnd] = "<?php endif; ?>";

            $startNode = $dom->createElement('spp-php');
            $startNode->setAttribute('id', $idStart);
            $endNode = $dom->createElement('spp-php');
            $endNode->setAttribute('id', $idEnd);

            $node->parentNode->insertBefore($startNode, $node);
            while ($node->childNodes->length > 0) {
                $node->parentNode->insertBefore($node->childNodes->item(0), $node);
            }
            $node->parentNode->insertBefore($endNode, $node);
            $node->parentNode->removeChild($node);
        }

        // Process spp-foreach
        $foreachNodes = $xpath->query('//spp-foreach');
        foreach ($foreachNodes as $node) {
            $loop = $node->getAttribute('loop');
            $idStart = 'PHP_BLOCK_' . count($phpBlocks);
            $phpBlocks[$idStart] = "<?php foreach ({$loop}): ?>";
            
            $idEnd = 'PHP_BLOCK_' . (count($phpBlocks));
            $phpBlocks[$idEnd] = "<?php endforeach; ?>";

            $startNode = $dom->createElement('spp-php');
            $startNode->setAttribute('id', $idStart);
            $endNode = $dom->createElement('spp-php');
            $endNode->setAttribute('id', $idEnd);

            $node->parentNode->insertBefore($startNode, $node);
            while ($node->childNodes->length > 0) {
                $node->parentNode->insertBefore($node->childNodes->item(0), $node);
            }
            $node->parentNode->insertBefore($endNode, $node);
            $node->parentNode->removeChild($node);
        }

        // Process spp-flash
        $flashNodes = $xpath->query('//spp-flash');
        foreach ($flashNodes as $node) {
            $key = $node->getAttribute('key');
            $id = 'PHP_BLOCK_' . count($phpBlocks);
            $phpBlocks[$id] = "<?php if (\\SPP\\SPPSession::hasFlash('{$key}')): echo '<div class=\"spp-flash spp-flash-{$key}\">' . htmlspecialchars(\\SPP\\SPPSession::getFlash('{$key}')) . '</div>'; endif; ?>";

            $phpNode = $dom->createElement('spp-php');
            $phpNode->setAttribute('id', $id);

            $node->parentNode->replaceChild($phpNode, $node);
        }

        // Process spp-lang and spp-trans
        $langNodes = $xpath->query('//spp-lang | //spp-trans');
        foreach ($langNodes as $node) {
            $key = $node->getAttribute('key');
            $id = 'PHP_BLOCK_' . count($phpBlocks);
            $phpBlocks[$id] = "<?php echo htmlspecialchars(\\SPPMod\\SPPLang\\SPPLang::getTranslation('{$key}', \\SPP\\SPPSession::get('locale', 'en')), ENT_QUOTES, 'UTF-8'); ?>";

            $phpNode = $dom->createElement('spp-php');
            $phpNode->setAttribute('id', $id);

            $node->parentNode->replaceChild($phpNode, $node);
        }

        // 4. Process <php-comp> elements via AST
        $components = $xpath->query('//php-comp');
        $appName = \SPP\Scheduler::getContext();

        foreach ($components as $comp) {
            $compName = $comp->getAttribute('name');
            $state = [];
            foreach ($comp->attributes as $attr) {
                if ($attr->nodeName !== 'name') {
                    $state[$attr->nodeName] = $attr->nodeValue;
                }
            }
            
            // Generate runtime JS resolution code
            $phpResolution = "<?php \\SPPMod\\SPPView\\ViewPage::resolveTieredJS('{$appName}', '{$compName}'); ?>";
            
            $div = $dom->createElement('div');
            $div->setAttribute('data-spp-component', $compName);
            $div->setAttribute('data-state', htmlspecialchars(json_encode($state), ENT_QUOTES, 'UTF-8'));
            
            // Append the PHP resolution script as a protected token
            $div->appendChild($dom->createTextNode('__SPP_RES_' . base64_encode($phpResolution) . '__'));

            $comp->parentNode->replaceChild($div, $comp);
        }

        // Output HTML while maintaining structure
        $compiledHtml = $dom->saveHTML();

        // 4. Restore PHP Blocks
        foreach ($phpBlocks as $id => $phpCode) {
            $compiledHtml = str_replace("<spp-php id=\"{$id}\"></spp-php>", $phpCode, $compiledHtml);
            $compiledHtml = str_replace("<spp-php id=\"{$id}\"/>", $phpCode, $compiledHtml); // self-closing fallback
        }
        
        // 5. Restore dynamic runtime resolutions for SPP-UX
        $compiledHtml = preg_replace_callback('/__SPP_RES_([A-Za-z0-9+\/=]+)__/', function($matches) {
            return base64_decode($matches[1]);
        }, $compiledHtml);

        // Save to cache
        file_put_contents($cacheFile, $compiledHtml);
        return $cacheFile;
    }
}
