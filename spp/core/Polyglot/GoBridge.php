<?php

namespace SPP\Core\Polyglot;

class GoBridge implements PolyglotBridgeInterface
{
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        return null;
    }

    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string
    {
        $binary = $runtime['path'];
        $moduleAbs = realpath($module) ?: $module;
        $moduleDir = dirname($moduleAbs);
        $moduleFile = basename($moduleAbs);
        $cdCmd = PHP_OS_FAMILY === 'Windows' ? "cd /D \"{$moduleDir}\"" : "cd \"{$moduleDir}\"";
        return "{$cdCmd} && \"{$binary}\" run \"{$moduleFile}\" \"{$func}\"";
    }
}
