<?php

namespace SPP\Core\Polyglot;

class DefaultBridge implements PolyglotBridgeInterface
{
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        return null;
    }

    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string
    {
        $binary = $runtime['path'];
        $lang = strtolower($runtime['name']);
        if (str_contains($lang, 'python')) {
            $ext = 'py';
        } elseif (str_contains($lang, 'perl')) {
            $ext = 'pl';
        } elseif (str_contains($lang, 'node')) {
            $ext = 'js';
        } else {
            $ext = '';
        }
        $frameworkLibDir = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'polyglot';
        $dispatchScript = $frameworkLibDir . DIRECTORY_SEPARATOR . 'dispatch.' . $ext;
        if (!file_exists($dispatchScript)) {
            throw new \RuntimeException("Dispatcher script for {$lang} not found.");
        }
        return "\"{$binary}\" \"{$dispatchScript}\" \"{$module}\" \"{$func}\"";
    }
}
