<?php

namespace SPP\Core\Polyglot;

class JavaBridge implements PolyglotBridgeInterface
{
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        return null;
    }

    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string
    {
        $binary = $runtime['path'];
        $javaLib = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'java';
        $cpSep = PHP_OS_FAMILY === 'Windows' ? ';' : ':';
        $argsJson = base64_encode(json_encode($args));
        return "\"{$binary}\" -cp \".{$cpSep}{$javaLib}\" \"{$module}\" \"{$func}\" \"{$argsJson}\"";
    }
}
