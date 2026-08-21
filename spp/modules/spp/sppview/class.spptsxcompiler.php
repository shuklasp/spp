<?php
namespace SPPMod\SPPView;

/**
 * Class SppTsxCompiler
 * 
 * Provides on-the-fly zero-build compilation of .tsx files.
 * Uses aggressive regex lexing to strip TypeScript types from JS files
 * so they can run natively in the browser without Node.js.
 */
class SppTsxCompiler
{
    /**
     * Compiles a .tsx file to pure .js dynamically.
     * 
     * @param string $tsxContent The raw TSX string.
     * @return string Pure JavaScript ready for the browser.
     */
    public static function compile(string $tsxContent): string
    {
        // 1. Strip interface and type declarations
        $js = preg_replace('/^(export\s+)?(interface|type)\s+[a-zA-Z0-9_]+\s*(=|\{)[^}]*\}?;?/sm', '', $tsxContent);
        
        // 2. Strip type assertions: "as SomeType"
        $js = preg_replace('/\s+as\s+[a-zA-Z0-9_<>]+/', '', $js);
        
        // 3. Strip function return types: "function foo(): string {" -> "function foo() {"
        $js = preg_replace('/(\([^\)]*\))\s*:\s*[a-zA-Z0-9_<>\[\]]+\s*\{/', '$1 {', $js);
        
        // 4. Strip parameter types: "function foo(a: string, b: number)" -> "function foo(a, b)"
        // Simplified parameter type stripping
        $js = preg_replace('/([a-zA-Z0-9_]+)\s*:\s*[a-zA-Z0-9_<>\[\]]+/', '$1', $js);
        
        return trim($js);
    }
}
