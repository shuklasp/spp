<?php

namespace SPP\Core\Polyglot;

class PolyglotBridgeFactory
{
    public static function getBridge(string $lang): PolyglotBridgeInterface
    {
        switch (strtolower($lang)) {
            case 'java':
                return new JavaBridge();
            case 'dotnet':
                return new DotNetBridge();
            case 'go':
                return new GoBridge();
            case 'compiler':
                return new CompilerBridge();
            default:
                return new DefaultBridge();
        }
    }
}
