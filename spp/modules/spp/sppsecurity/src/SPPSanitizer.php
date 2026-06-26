<?php
namespace SPPMod\SPPSecurity;

class SPPSanitizer
{
    /**
     * Sanitize input based on output context to prevent XSS.
     * 
     * @param string $input 
     * @param string $context 'html', 'attribute', 'javascript', 'url'
     */
    public function sanitize(string $input, string $context = 'html'): string
    {
        switch ($context) {
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            case 'attribute':
                return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            case 'javascript':
                // Basic JSON encoding prevents breaking out of JS string literals safely
                return json_encode($input, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
            case 'url':
                return rawurlencode($input);
            default:
                return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }
}
