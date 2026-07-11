<?php

namespace SPP\Core\Polyglot;

class DotNetBridge implements PolyglotBridgeInterface
{
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        return null;
    }

    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string
    {
        $binary = $runtime['path'];
        if (is_dir($module) || str_ends_with($module, '.csproj')) {
            return "\"{$binary}\" run --project \"{$module}\"";
        } else {
            return "\"{$binary}\" \"{$module}\"";
        }
    }
}
