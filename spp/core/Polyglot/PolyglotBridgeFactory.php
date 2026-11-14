<?php

namespace SPP\Core\Polyglot;

class PolyglotBridgeFactory
{
    public static function getBridge(string $lang): PolyglotBridgeInterface
    {
        $normalized = strtolower($lang);

        switch ($normalized) {
            case 'java':
                return new JavaBridge();
            case 'dotnet':
                return new DotNetBridge();
            case 'go':
                return new GoBridge();
            case 'compiler':
                return new CompilerBridge();
        }

        // Check if dynamic driver exists in LanguageRegistry (e.g. rust, zig, ruby, elixir, custom DSLs)
        if (LanguageRegistry::getDriver($normalized) !== null) {
            return new UniversalDynamicBridge($normalized);
        }

        return new DefaultBridge();
    }
}
